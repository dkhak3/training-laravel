<?php declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\TextUI;

use const PHP_EOL;
use function is_file;
use function is_readable;
use function printf;
use function realpath;
use function sprintf;
use function trim;
use function unlink;
use PHPUnit\Event\Facade as EventFacade;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Logging\EventLogger;
use PHPUnit\Logging\JUnit\JunitXmlLogger;
use PHPUnit\Logging\TeamCity\TeamCityLogger;
use PHPUnit\Logging\TestDox\HtmlRenderer as TestDoxHtmlRenderer;
use PHPUnit\Logging\TestDox\PlainTextRenderer as TestDoxTextRenderer;
use PHPUnit\Logging\TestDox\TestResultCollector as TestDoxResultCollector;
use PHPUnit\Runner\CodeCoverage;
use PHPUnit\Runner\Extension\ExtensionBootstrapper;
use PHPUnit\Runner\Extension\Facade as ExtensionFacade;
use PHPUnit\Runner\Extension\PharLoader;
use PHPUnit\Runner\ResultCache\DefaultResultCache;
use PHPUnit\Runner\ResultCache\NullResultCache;
use PHPUnit\Runner\ResultCache\ResultCache;
use PHPUnit\Runner\ResultCache\ResultCacheHandler;
use PHPUnit\Runner\TestSuiteSorter;
use PHPUnit\Runner\Version;
use PHPUnit\TestRunner\TestResult\Facade as TestResultFacade;
use PHPUnit\TextUI\CliArguments\Builder;
use PHPUnit\TextUI\CliArguments\Configuration as CliConfiguration;
use PHPUnit\TextUI\CliArguments\Exception as ArgumentsException;
use PHPUnit\TextUI\CliArguments\XmlConfigurationFileFinder;
use PHPUnit\TextUI\Command\AtLeastVersionCommand;
use PHPUnit\TextUI\Command\GenerateConfigurationCommand;
use PHPUnit\TextUI\Command\ListGroupsCommand;
use PHPUnit\TextUI\Command\ListTestsAsTextCommand;
use PHPUnit\TextUI\Command\ListTestsAsXmlCommand;
use PHPUnit\TextUI\Command\ListTestSuitesCommand;
use PHPUnit\TextUI\Command\MigrateConfigurationCommand;
use PHPUnit\TextUI\Command\Result;
use PHPUnit\TextUI\Command\ShowHelpCommand;
use PHPUnit\TextUI\Command\ShowVersionCommand;
use PHPUnit\TextUI\Command\VersionCheckCommand;
use PHPUnit\TextUI\Command\WarmCodeCoverageCacheCommand;
use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\PhpHandler;
use PHPUnit\TextUI\Configuration\Registry;
use PHPUnit\TextUI\Configuration\TestSuiteBuilder;
use PHPUnit\TextUI\Output\DefaultPrinter;
use PHPUnit\TextUI\Output\Facade as OutputFacade;
use PHPUnit\TextUI\Output\Printer;
use PHPUnit\TextUI\XmlConfiguration\Configuration as XmlConfiguration;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use SebastianBergmann\Timer\Timer;
use Throwable;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class Application
{
    public function run(array $argv): int
    {
        try {
            EventFacade::emitter()->applicationStarted();

            $cliConfiguration           = $this->buildCliConfiguration($argv);
            $pathToXmlConfigurationFile = (new XmlConfigurationFileFinder)->find($cliConfiguration);

            $this->executeCommandsThatOnlyRequireCliConfiguration($cliConfiguration, $pathToXmlConfigurationFile);

            $xmlConfiguration = $this->loadXmlConfiguration($pathToXmlConfigurationFile);

            $configuration = Registry::init(
                $cliConfiguration,
                $xmlConfiguration
            );

            (new PhpHandler)->handle($configuration->php());

            if ($configuration->hasBootstrap()) {
                $this->loadBootstrapScript($configuration->bootstrap());
            }

            $this->executeCommandsThatRequireCompleteConfiguration($configuration, $cliConfiguration);

            $testSuite = $this->buildTestSuite($configuration);

            $this->executeCommandsThatRequireCliConfigurationAndTestSuite($cliConfiguration, $testSuite);
            $this->executeHelpCommandWhenThereIsNothingElseToDo($configuration, $testSuite);

            $pharExtensions = null;

            if (!$configuration->noExtensions()) {
                if ($configuration->hasPharExtensionDirectory()) {
                    $pharExtensions = (new PharLoader)->loadPharExtensionsInDirectory(
                        $configuration->pharExtensionDirectory()
                    );
                }

                $this->bootstrapExtensions($configuration);
            }

            CodeCoverage::instance()->init($configuration, CodeCoverageFilterRegistry::instance());

            $printer = OutputFacade::init($configuration);

            $this->writeRuntimeInformation($printer, $configuration);
            $this->writePharExtensionInformation($printer, $pharExtensions);
            $this->writeRandomSeedInformation($printer, $configuration);

            $printer->print(PHP_EOL);

            $this->registerLogfileWriters($configuration);

            $testDoxResultCollector = $this->testDoxResultCollector($configuration);

            TestResultFacade::init();

            $resultCache = $this->initializeTestResultCache($configuration);

            EventFacade::seal();

            $timer = new Timer;
            $timer->start();

            $runner = new TestRunner;

            $runner->run(
                $configuration,
                $resultCache,
                $testSuite
            );

            $duration = $timer->stop();

            $testDoxResult = null;

            if (isset($testDoxResultCollector)) {
                $testDoxResult = $testDoxResultCollector->testMethodsGroupedByClass();
            }

            if ($testDoxResult !== null &&
                $configuration->hasLogfileTestdoxHtml()) {
                OutputFacade::printerFor($configuration->logfileTestdoxHtml())->print(
                    (new TestDoxHtmlRenderer)->render($testDoxResult)
                );
            }

            if ($testDoxResult !== null &&
                $configuration->hasLogfileTestdoxText()) {
                OutputFacade::printerFor($configuration->logfileTestdoxText())->print(
                    (new TestDoxTextRenderer)->render($testDoxResult)
                );
            }

            $result = TestResultFacade::result();

            OutputFacade::printResult($result, $testDoxResult, $duration);
            CodeCoverage::instance()->generateReports($printer, $configuration);

            $shellExitCode = (new ShellExitCodeCalculator)->calculate(
                $configuration->failOnEmptyTestSuite(),
                $configuration->failOnRisky(),
                $configuration->failOnWarning(),
                $configuration->failOnIncomplete(),
                $configuration->failOnSkipped(),
                $result
            );

            EventFacade::emitter()->applicationFinished($shellExitCode);

            return $shellExitCode;
        } catch (Throwable $t) {
            $this->exitWithCrashMessage($t);
        }
    }

    private function exitWithCrashMessage(Throwable $t): never
    {
        $message = $t->getMessage();

        if (empty(trim($message))) {
            $message = '(no message)';
        }

        printf(
            '%s%sAn error occurred inside PHPUnit.%s%sMessage:  %s',
            PHP_EOL,
            PHP_EOL,
            PHP_EOL,
            PHP_EOL,
            $message
        );

        $first = true;

        do {
            printf(
                '%s%s: %s:%d%s%s%s%s',
                PHP_EOL,
                $first ? 'Location' : 'Caused by',
                $t->getFile(),
                $t->getLine(),
                PHP_EOL,
                PHP_EOL,
                $t->getTraceAsString(),
                PHP_EOL
            );

            $first = false;
        } while ($t = $t->getPrevious());

        exit(Result::CRASH);
    }

    private function exitWithErrorMessage(string $message): never
    {
        print Version::getVersionString() . PHP_EOL . PHP_EOL . $message . PHP_EOL;

        exit(Result::EXCEPTION);
    }

    private function execute(Command\Command $comm   Ğ        jš   ›              
    Ğ      8à¥€úÿÿ8p˜€úÿÿ4  ‚ «     ÜÂâøD3c†
1Œv4öj   P  \     @ €E « 4  ‚ «     ÜÂâøD3c†
1ŒvÃCk   P  \     @ €c h 4  ‚ «     ÜÂâøD3c†
1Œv@ k   P  \     @ €\  4  ‚ «     ÜÂâøD3c†
1ŒvKÌk   P  \     @ €AÎ4  ‚ «     ÜÂâøD3c†
1Œv°$l   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1Œv…Hm   P  \     @ €F i 4  ‚ «     ÜÂâøD3c†
1Œvïu   P  \     @ €“ªÉê4  ‚ «     ÜÂâøD3c†
1ŒvsBv   P  \     @ €V o 4  ‚ «     ÜÂâøD3c†
1ŒvNyv   P  \     @ €I 8 4  ‚ «     ÜÂâøD3c†
1Œv5¹v   P  \     @ €š,´í4  ‚ «     ÜÂâøD3c†
1ŒvU÷v   P  \     @ €az´í4  ‚ «     ÜÂâøD3c†
1Œv!(w   P  \     @ €{´í4  ‚ «     ÜÂâøD3c†
1ŒvúUw   P  \     @ €N‚Íí4  ‚ «     ÜÂâøD3c†
1Œvlr|   P  \     @ €ªãÍí4  ‚ «     ÜÂâøD3c†
1Œvêr}   P  \     @ €r.Îí4  ‚ «     ÜÂâøD3c†
1Œvû}   P  \     @ €ü˜Îí4  ‚ «     ÜÂâøD3c†
1ŒvË}   P  \     @ €[ÙÎí4  ‚ «     ÜÂâøD3c†
1Œv°ø}   P  \     @ €Ñ…ı4  ‚ «     ÜÂâøD3c†
1Œvº#~   P  \     @ € « 4  ‚ «     ÜÂâøD3c†
1Œv­M~   P  \     @ €Ã/4  ‚ «     ÜÂâøD3c†
1Œvb£~   P  \     @ €ïëõO4  ‚ «     ÜÂâøD3c†
1ŒvŸĞ~   P  \     @ €ğı4  ‚ «     ÜÂâøD3c†
1Œv*   P  \     @ €E « 4  ‚ «     ÜÂâøD3c†
1ŒváS   P  \     @ €c h 4  ‚ «     ÜÂâøD3c†
1ŒvN}   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1Œv %‚   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1Œvü‚   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1ŒvW*ƒ   P  \     @ €AÎ4  ‚ «     ÜÂâøD3c†
1Œv…„   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1Œv•3„   P  \     @ €\ D 4  ‚ «     ÜÂâøD3c†
1Œvc„   P  \     @ €o w 4  ‚ «     ÜÂâøD3c†
1ŒvĞ„   P  \     @ €c h 4  ‚ «     ÜÂâøD3c†
1ŒvÉü„   P  \     @ €Ô  4  ‚ «     ÜÂâøD3c†
1Œv{f…   P  \     @ €Ô  4  ‚ «     ÜÂâøD3c†
1Œvº›…   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1ŒvGÔ…   P  \     @ €i c 4  ‚ «     ÜÂâøD3c†
1Œv<†   P  \     @ €I n 4  ‚ «     ÜÂâøD3c†
1Œvn†   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1ŒvhŸ†   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œv}Ğ†   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1ŒvÒ
ˆ   P  \     @ €L « 4  ‚ «     ÜÂâøD3c†
1Œv©Aˆ   P  \     @ €H a 4  ‚ «     ÜÂâøD3c†
1Œv‡Ì‰   P  \     @ €a l 4  ‚ «     ÜÂâøD3c†
1Œv¢ı‰   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1Œvp7Š   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1Œv'ÃŠ   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1ŒvtîŠ   P  \     @ €±$¨
4  ‚ «     ÜÂâøD3c†
1Œv°‹   P  \     @ €d i 4  ‚ «     ÜÂâøD3c†
1ŒvS‹   P  \     @ €r \ 4  ‚ «     ÜÂâøD3c†
1ŒvÇˆ‹   P  \     @ €c†
14  ‚ «     ÜÂâøD3c†
1ŒvÉ´‹   P  \     @ €c†
14  ‚ «     ÜÂâøD3c†
1Œvà‹   P  \     @ €c†
14  ‚ «     ÜÂâøD3c†
1Œv×ÍŒ   P  \     @ €“ªÉê4  ‚ «     ÜÂâøD3c†
1Œv%5   P  \     @ €V o 4  ‚ «     ÜÂâøD3c†
1ŒvMm   P  \     @ €I 3 4  ‚ «     ÜÂâøD3c†
1Œvà$   P  \     @ €D¦4  ‚ «     ÜÂâøD3c†
1ŒvT   P  \     @ €®Q¦4  ‚ «     ÜÂâøD3c†
1Œv¦>   P  \     @ €]R¦4  ‚ «     ÜÂâøD3c†
1Œv4x   P  \     @ €³T§4  ‚ «     ÜÂâøD3c†
1ŒvFµ”   P  \     @ €m e 4  ‚ «     ÜÂâøD3c†
1Œv·ì”   P  \     @ €8 . 4  ‚ «     ÜÂâøD3c†
1Œv"<•   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1Œv¯{•   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1ŒvË—   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1ŒvRJ—   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1Œv÷u—   P  \     @ €W i 4  ‚ «     ÜÂâøD3c†
1ŒvÃ¨—   P  \     @ €p   4  ‚ «     ÜÂâøD3c†
1ŒvªA˜   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1Œv¹{˜   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1ŒvÊ¶˜   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1Œvgì˜   P  \     @ €ÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿ   Ğ        ûøÃ                 
    Ğ      8àü€úÿÿ8 ¤€úÿÿ4  ‚ «     ÜÂâøD3c†
1Œv(í   P  \     @ €> « 4  ‚ «     ÜÂâøD3c†
1Œv0   P  \     @ €púû@4  ‚ «     ÜÂâøD3c†
1Œv]   P  \     @ €h¢½4  ‚ «     ÜÂâøD3c†
1Œv€ˆ   P  \     @ €€. 4  ‚ «     ÜÂâøD3c†
1Œv‹³   P  \     @ €@  4  ‚ «     ÜÂâøD3c†
1ŒvêŞ   P  \     @ €@  4  ‚ «     ÜÂâøD3c†
1ŒvÄ’   P  \     @ €i c 4  ‚ «     ÜÂâøD3c†
1Œv¤:’   P  \     @ €I n 4  ‚ «     ÜÂâøD3c†
1ŒvF„’   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1ŒvÜÁ’   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œvı’   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œv×5“   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œvœ›“   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1ŒvGØ“   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œvª•   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œv…Ù•   P  \     @ €L « 4  ‚ «     ÜÂâøD3c†
1Œv–   P  \     @ €H a 4  ‚ «     ÜÂâøD3c†
1Œvp1–   P  \     @ €a l 4  ‚ «     ÜÂâøD3c†
1Œvšh–   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1Œv²”–   P  \     @ €)z‡4  ‚ «     ÜÂâøD3c†
1ŒvÀ–   P  \     @ €K%È%4  ‚ «     ÜÂâøD3c†
1ŒvŸî–   P  \     @ €“ÙJë4  ‚ «     ÜÂâøD3c†
1Œvá—   P  \     @ €@  4  ‚ «     ÜÂâøD3c†
1ŒvÑ˜   P  \     @ €AÎ4  ‚ «     ÜÂâøD3c†
1Œv¿&™   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1Œv_™   P  \     @ €F i 4  ‚ «     ÜÂâøD3c†
1Œv™™   P  \     @ €h¢½4  ‚ «     ÜÂâøD3c†
1Œv‹Ò™   P  \     @ €€ÎB4  ‚ «     ÜÂâøD3c†
1Œvç7   P  \     @ €  W 4  ‚ «     ÜÂâøD3c†
1Œv&e   P  \     @ €P»0ì4  ‚ «     ÜÂâøD3c†
1ŒvÆ   P  \     @ €H  4  ‚ «     ÜÂâøD3c†
1ŒvƒÌ   P  \     @ €€4  ‚ «     ÜÂâøD3c†
1ŒvÏö   P  \     @ €  W 4  ‚ «     ÜÂâøD3c†
1Œvn#   P  \     @ €=¼0ì4  ‚ «     ÜÂâøD3c†
1Œv©O   P  \     @ €c<_ì4  ‚ «     ÜÂâøD3c†
1ŒvÌû   P  \     @ €H  4  ‚ «     ÜÂâøD3c†
1ŒvÉ*Ÿ   P  \     @ €€4  ‚ «     ÜÂâøD3c†
1ŒvËä£   P  \     @ €  W 4  ‚ «     ÜÂâøD3c†
1Œvô¥   P  \     @ €š—fì4  ‚ «     ÜÂâøD3c†
1Œvş ¦   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œv0v®   P  \     @ €E « 4  ‚ «     ÜÂâøD3c†
1Œvq£¯   P  \     @ €c h 4  ‚ «     ÜÂâøD3c†
1Œvb‚°   P  \     @ €\  4  ‚ «     ÜÂâøD3c†
1Œvæ®°   P  \     @ €Í7¶4  ‚ «     ÜÂâøD3c†
1Œvã°   P  \     @ €h„©4  ‚ «     ÜÂâøD3c†
1Œvg±   P  \     @ €µ:cá4  ‚ «     ÜÂâøD3c†
1ŒvWb´   P  \     @ €ÿ'=4  ‚ «     ÜÂâøD3c†
1Œv"¶   P  \     @ €‡¤î4  ‚ «     ÜÂâøD3c†
1Œv_º   P  \     @ €
gı4  ‚ «     ÜÂâøD3c†
1Œv]Iº   P  \     @ €E « 4  ‚ «     ÜÂâøD3c†
1ŒvÏ»   P  \     @ €c h 4  ‚ «     ÜÂâøD3c†
1Œv˜ú»   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1Œvé)¼   P  \     @ €W i 4  ‚ «     ÜÂâøD3c†
1Œv7¼   P  \     @ €p   4  ‚ «     ÜÂâøD3c†
1Œv¿½   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1Œv¬A½   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1ŒvÏ½   P  \     @ €   4  ‚ «     ÜÂâøD3c†
1ŒvÚú½   P  \     @ €F i 4  ‚ «     ÜÂâøD3c†
1Œv“%¾   P  \     @ €h¢½4  ‚ «     ÜÂâøD3c†
1Œv¨Ñ¾   P  \     @ €€  4  ‚ «     ÜÂâøD3c†
1Œv“¿   P  \     @ €  W 4  ‚ «     ÜÂâøD3c†
1Œv­0¿   P  \     @ €î:4  ‚ «     ÜÂâøD3c†
1Œv=[¿   P  \     @ €H  4  ‚ «     ÜÂâøD3c†
1Œv †¿   P  \     @ €€4  ‚ «     ÜÂâøD3c†
1Œv)cÀ   P  \     @ €  W 4  ‚ «     ÜÂâøD3c†
1ŒvuÀ   P  \     @ €Ü7V4  ‚ «     ÜÂâøD3c†
1Œv!ÛÁ   P  \     @ €E « 4  ‚ «     ÜÂâøD3c†
1ŒvÓ
Â   P  \     @ €c h 4  ‚ «     ÜÂâøD3c†
1Œvª?Â   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1ŒvÀvÂ   P  \     @ €P  4  ‚ «     ÜÂâøD3c†
1Œv²ÍÃ   P  \     @ €P  ÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿÿ   Ğ        55Ô   œ               
    Ğ      8°İ€úÿÿ8àü€úÿÿ4  ‚ «     ÜÂâøD3c†
1Œv ú   P  \     @ €i c 4  ‚ «     ÜÂâøD3c†
1ŒvO‚   P  \     @ €I n 4  ‚ «     ÜÂâøD3c†
1Œvçy‚   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1ŒvâÏ‚   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1Œv¹‡   P  \     @ €‚ « 4  ‚ «     ÜÂâøD3c†
1ŒvD‡   P  \     @ €L « 4  ‚ «     ÜÂâøD3c†
1Œvİ‡   P  \     @ €H a 4  ‚ «     ÜÂâøD3c†
1Œvø®‡   P  \     @ €a l 4  ‚ «     ÜÂâøD3c†
1ŒvuÚ‡   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1ŒvÒ‰   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1Œv³5‰   P  \     @ €ÜÂ4  ‚ «     ÜÂâøD3c†
1Œvc‰   P  \     @ €±$¨
4  ‚ «     ÜÂâøD3c†
1Œvv‰   P  \     @ €d i 4  ‚ «     ÜÂâøD3c†
1ŒvrsŒ   P  \     @ €r \ 4  ‚ «     ÜÂâøD3c†
1Œv	¡Œ   P  \     @ €c†
14  ‚ «     ÜÂâøD3c†
1Œvõ±   P  \     @ €c†
14  ‚ «     ÜÂâøD3c†
1ŒvÅá   P  \     @ €c†
14  ‚ «     ÜÂâøD3c†
1Œv   P  \     @ €“ªÉê4  ‚ «     ÜÂâøD3c†
1Œv±­   P  \