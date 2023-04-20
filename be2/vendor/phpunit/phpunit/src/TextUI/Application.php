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

    private function execute(Command\Command $comm   �        j�
    �      8�����8p�����4  �� �     ����D3c�
1�v�4�j
1�v��Ck
1�v�@�k
1�v�K�k
1�v��$l
1�v��Hm
1�v��u
1�v�sBv
1�v�Nyv
1�v�5�v
1�v�U�v
1�v�!(w
1�v��Uw
1�v�lr|
1�v��r}
1�v���}
1�v��}
1�v���}
1�v��#~
1�v��M~
1�v�b�~
1�v���~
1�v�*�
1�v��S�
1�v�N}�
1�v� %�
1�v���
1�v�W*�
1�v���
1�v��3�
1�v�c�
1�v�Є
1�v����
1�v�{f�
1�v����
1�v�Gԅ
1�v�<�
1�v�n�
1�v�h��
1�v�}І
1�v��
�
1�v��A�
1�v��̉
1�v����
1�v�p7�
1�v�'Ê
1�v�t�
4  �� �     ����D3c�
1�v���
1�v�
1�v�ǈ�
14  �� �     ����D3c�
1�v�ɴ�
14  �� �     ����D3c�
1�v���
14  �� �     ����D3c�
1�v��͌
1�v�%5�
1�v�Mm�
1�v��$�
1�v�T�
1�v��>�
1�v�4x�
1�v�F��
1�v���
1�v�"<�
1�v��{�
1�v���
1�v�RJ�
1�v��u�
1�v�è�
1�v��A�
1�v��{�
1�v�ʶ�
1�v�g�
    �      8������8������4  �� �     ����D3c�
1�v�(�
1�v�0�
1�v��]�
1�v����
1�v����
1�v��ސ
1�v���
1�v��:�
1�v�F��
1�v����
1�v���
1�v��5�
1�v����
1�v�Gؓ
1�v���
1�v��ٕ
1�v��
1�v�p1�
1�v��h�
1�v����
1�v���
1�v���
1�v��
1�v���
1�v��&�
1�v�_�
1�v���
1�v��ҙ
1�v��7�
1�v�&e�
1�v�ƞ�
1�v��̝
1�v����
1�v�n#�
1�v��O�
1�v����
1�v��*�
1�v���
1�v���
1�v�� �
1�v�0v�
1�v�q��
1�v�b��
1�v�殰
1�v��
1�v�g�
1�v�Wb�
1�v�"��
1�v�_�
g�4  �� �     ����D3c�
1�v�]I�
1�v�ϻ
1�v����
1�v��)�
1�v�7��
1�v���
1�v��A�
1�v�Ͻ
1�v����
1�v��%�
1�v��Ѿ
1�v���
1�v��0�
1�v�=[�
1�v� ��
1�v�)c�
1�v�u��
1�v�!��
1�v��
�
1�v��?�
1�v��v�
1�v����
    �      8������8������4  �� �     ����D3c�
1�v����
1�v�
1�v��y�
1�v��ς
1�v���
1�v�D�
1�v���
1�v����
1�v�uڇ
1�v���
1�v��5�
1�v��c�
4  �� �     ����D3c�
1�v�v��
1�v�rs�
1�v�	��
14  �� �     ����D3c�
1�v����
14  �� �     ����D3c�
1�v���
14  �� �     ����D3c�
1�v���
1�v����