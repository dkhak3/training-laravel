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

    private function execute(Command\Command $comm   �        j�   �              
    �      8�����8p�����4  �� �     ����D3c�
1�v�4�j   P  \     @ �E � 4  �� �     ����D3c�
1�v��Ck   P  \     @ �c h 4  �� �     ����D3c�
1�v�@�k   P  \     @ �\  4  �� �     ����D3c�
1�v�K�k   P  \     @ �A��4  �� �     ����D3c�
1�v��$l   P  \     @ �   4  �� �     ����D3c�
1�v��Hm   P  \     @ �F i 4  �� �     ����D3c�
1�v��u   P  \     @ �����4  �� �     ����D3c�
1�v�sBv   P  \     @ �V o 4  �� �     ����D3c�
1�v�Nyv   P  \     @ �I 8 4  �� �     ����D3c�
1�v�5�v   P  \     @ ��,��4  �� �     ����D3c�
1�v�U�v   P  \     @ �az��4  �� �     ����D3c�
1�v�!(w   P  \     @ �{��4  �� �     ����D3c�
1�v��Uw   P  \     @ �N���4  �� �     ����D3c�
1�v�lr|   P  \     @ �����4  �� �     ����D3c�
1�v��r}   P  \     @ �r.��4  �� �     ����D3c�
1�v���}   P  \     @ �����4  �� �     ����D3c�
1�v��}   P  \     @ �[���4  �� �     ����D3c�
1�v���}   P  \     @ �х�4  �� �     ����D3c�
1�v��#~   P  \     @ � � 4  �� �     ����D3c�
1�v��M~   P  \     @ ��/4  �� �     ����D3c�
1�v�b�~   P  \     @ ����O4  �� �     ����D3c�
1�v���~   P  \     @ ���4  �� �     ����D3c�
1�v�*�   P  \     @ �E � 4  �� �     ����D3c�
1�v��S�   P  \     @ �c h 4  �� �     ����D3c�
1�v�N}�   P  \     @ �P  4  �� �     ����D3c�
1�v� %�   P  \     @ ���4  �� �     ����D3c�
1�v���   P  \     @ ���4  �� �     ����D3c�
1�v�W*�   P  \     @ �A��4  �� �     ����D3c�
1�v���   P  \     @ �   4  �� �     ����D3c�
1�v��3�   P  \     @ �\ D 4  �� �     ����D3c�
1�v�c�   P  \     @ �o w 4  �� �     ����D3c�
1�v�Є   P  \     @ �c h 4  �� �     ����D3c�
1�v����   P  \     @ ��  4  �� �     ����D3c�
1�v�{f�   P  \     @ ��  4  �� �     ����D3c�
1�v����   P  \     @ �   4  �� �     ����D3c�
1�v�Gԅ   P  \     @ �i c 4  �� �     ����D3c�
1�v�<�   P  \     @ �I n 4  �� �     ����D3c�
1�v�n�   P  \     @ �� � 4  �� �     ����D3c�
1�v�h��   P  \     @ �� � 4  �� �     ����D3c�
1�v�}І   P  \     @ �� � 4  �� �     ����D3c�
1�v��
�   P  \     @ �L � 4  �� �     ����D3c�
1�v��A�   P  \     @ �H a 4  �� �     ����D3c�
1�v��̉   P  \     @ �a l 4  �� �     ����D3c�
1�v����   P  \     @ ���4  �� �     ����D3c�
1�v�p7�   P  \     @ ���4  �� �     ����D3c�
1�v�'Ê   P  \     @ ���4  �� �     ����D3c�
1�v�t�   P  \     @ ��$�
4  �� �     ����D3c�
1�v���   P  \     @ �d i 4  �� �     ����D3c�
1�v�S�   P  \     @ �r \ 4  �� �     ����D3c�
1�v�ǈ�   P  \     @ �c�
14  �� �     ����D3c�
1�v�ɴ�   P  \     @ �c�
14  �� �     ����D3c�
1�v���   P  \     @ �c�
14  �� �     ����D3c�
1�v��͌   P  \     @ �����4  �� �     ����D3c�
1�v�%5�   P  \     @ �V o 4  �� �     ����D3c�
1�v�Mm�   P  \     @ �I 3 4  �� �     ����D3c�
1�v��$�   P  \     @ �D�4  �� �     ����D3c�
1�v�T�   P  \     @ ��Q�4  �� �     ����D3c�
1�v��>�   P  \     @ �]R�4  �� �     ����D3c�
1�v�4x�   P  \     @ ��T�4  �� �     ����D3c�
1�v�F��   P  \     @ �m e 4  �� �     ����D3c�
1�v���   P  \     @ �8 . 4  �� �     ����D3c�
1�v�"<�   P  \     @ �P  4  �� �     ����D3c�
1�v��{�   P  \     @ �P  4  �� �     ����D3c�
1�v���   P  \     @ �P  4  �� �     ����D3c�
1�v�RJ�   P  \     @ �P  4  �� �     ����D3c�
1�v��u�   P  \     @ �W i 4  �� �     ����D3c�
1�v�è�   P  \     @ �p   4  �� �     ����D3c�
1�v��A�   P  \     @ �   4  �� �     ����D3c�
1�v��{�   P  \     @ �   4  �� �     ����D3c�
1�v�ʶ�   P  \     @ �   4  �� �     ����D3c�
1�v�g�   P  \     @ �����������������������������������������������������   �        ���   �              
    �      8������8������4  �� �     ����D3c�
1�v�(�   P  \     @ �> � 4  �� �     ����D3c�
1�v�0�   P  \     @ �p��@4  �� �     ����D3c�
1�v��]�   P  \     @ �h���4  �� �     ����D3c�
1�v����   P  \     @ ��. 4  �� �     ����D3c�
1�v����   P  \     @ �@  4  �� �     ����D3c�
1�v��ސ   P  \     @ �@  4  �� �     ����D3c�
1�v���   P  \     @ �i c 4  �� �     ����D3c�
1�v��:�   P  \     @ �I n 4  �� �     ����D3c�
1�v�F��   P  \     @ �� � 4  �� �     ����D3c�
1�v����   P  \     @ �� � 4  �� �     ����D3c�
1�v���   P  \     @ �� � 4  �� �     ����D3c�
1�v��5�   P  \     @ �� � 4  �� �     ����D3c�
1�v����   P  \     @ �� � 4  �� �     ����D3c�
1�v�Gؓ   P  \     @ �� � 4  �� �     ����D3c�
1�v���   P  \     @ �� � 4  �� �     ����D3c�
1�v��ٕ   P  \     @ �L � 4  �� �     ����D3c�
1�v��   P  \     @ �H a 4  �� �     ����D3c�
1�v�p1�   P  \     @ �a l 4  �� �     ����D3c�
1�v��h�   P  \     @ ���4  �� �     ����D3c�
1�v����   P  \     @ �)z�4  �� �     ����D3c�
1�v���   P  \     @ �K%�%4  �� �     ����D3c�
1�v���   P  \     @ ���J�4  �� �     ����D3c�
1�v��   P  \     @ �@  4  �� �     ����D3c�
1�v���   P  \     @ �A��4  �� �     ����D3c�
1�v��&�   P  \     @ �   4  �� �     ����D3c�
1�v�_�   P  \     @ �F i 4  �� �     ����D3c�
1�v���   P  \     @ �h���4  �� �     ����D3c�
1�v��ҙ   P  \     @ ���B4  �� �     ����D3c�
1�v��7�   P  \     @ �  W 4  �� �     ����D3c�
1�v�&e�   P  \     @ �P�0�4  �� �     ����D3c�
1�v�ƞ�   P  \     @ �H  4  �� �     ����D3c�
1�v��̝   P  \     @ ���4  �� �     ����D3c�
1�v����   P  \     @ �  W 4  �� �     ����D3c�
1�v�n#�   P  \     @ �=�0�4  �� �     ����D3c�
1�v��O�   P  \     @ �c<_�4  �� �     ����D3c�
1�v����   P  \     @ �H  4  �� �     ����D3c�
1�v��*�   P  \     @ ���4  �� �     ����D3c�
1�v���   P  \     @ �  W 4  �� �     ����D3c�
1�v���   P  \     @ ���f�4  �� �     ����D3c�
1�v�� �   P  \     @ �� � 4  �� �     ����D3c�
1�v�0v�   P  \     @ �E � 4  �� �     ����D3c�
1�v�q��   P  \     @ �c h 4  �� �     ����D3c�
1�v�b��   P  \     @ �\  4  �� �     ����D3c�
1�v�殰   P  \     @ ��7�4  �� �     ����D3c�
1�v��   P  \     @ �h��4  �� �     ����D3c�
1�v�g�   P  \     @ ��:c�4  �� �     ����D3c�
1�v�Wb�   P  \     @ ���'=4  �� �     ����D3c�
1�v�"��   P  \     @ ����4  �� �     ����D3c�
1�v�_�   P  \     @ �
g�4  �� �     ����D3c�
1�v�]I�   P  \     @ �E � 4  �� �     ����D3c�
1�v�ϻ   P  \     @ �c h 4  �� �     ����D3c�
1�v����   P  \     @ �P  4  �� �     ����D3c�
1�v��)�   P  \     @ �W i 4  �� �     ����D3c�
1�v�7��   P  \     @ �p   4  �� �     ����D3c�
1�v���   P  \     @ �   4  �� �     ����D3c�
1�v��A�   P  \     @ �   4  �� �     ����D3c�
1�v�Ͻ   P  \     @ �   4  �� �     ����D3c�
1�v����   P  \     @ �F i 4  �� �     ����D3c�
1�v��%�   P  \     @ �h���4  �� �     ����D3c�
1�v��Ѿ   P  \     @ ��  4  �� �     ����D3c�
1�v���   P  \     @ �  W 4  �� �     ����D3c�
1�v��0�   P  \     @ ��:4  �� �     ����D3c�
1�v�=[�   P  \     @ �H  4  �� �     ����D3c�
1�v� ��   P  \     @ ���4  �� �     ����D3c�
1�v�)c�   P  \     @ �  W 4  �� �     ����D3c�
1�v�u��   P  \     @ ��7V4  �� �     ����D3c�
1�v�!��   P  \     @ �E � 4  �� �     ����D3c�
1�v��
�   P  \     @ �c h 4  �� �     ����D3c�
1�v��?�   P  \     @ �P  4  �� �     ����D3c�
1�v��v�   P  \     @ �P  4  �� �     ����D3c�
1�v����   P  \     @ �P  ������������������������������������������������   �        55�   �               
    �      8������8������4  �� �     ����D3c�
1�v����   P  \     @ �i c 4  �� �     ����D3c�
1�v�O�   P  \     @ �I n 4  �� �     ����D3c�
1�v��y�   P  \     @ �� � 4  �� �     ����D3c�
1�v��ς   P  \     @ �� � 4  �� �     ����D3c�
1�v���   P  \     @ �� � 4  �� �     ����D3c�
1�v�D�   P  \     @ �L � 4  �� �     ����D3c�
1�v���   P  \     @ �H a 4  �� �     ����D3c�
1�v����   P  \     @ �a l 4  �� �     ����D3c�
1�v�uڇ   P  \     @ ���4  �� �     ����D3c�
1�v���   P  \     @ ���4  �� �     ����D3c�
1�v��5�   P  \     @ ���4  �� �     ����D3c�
1�v��c�   P  \     @ ��$�
4  �� �     ����D3c�
1�v�v��   P  \     @ �d i 4  �� �     ����D3c�
1�v�rs�   P  \     @ �r \ 4  �� �     ����D3c�
1�v�	��   P  \     @ �c�
14  �� �     ����D3c�
1�v����   P  \     @ �c�
14  �� �     ����D3c�
1�v���   P  \     @ �c�
14  �� �     ����D3c�
1�v���   P  \     @ �����4  �� �     ����D3c�
1�v����   P  \