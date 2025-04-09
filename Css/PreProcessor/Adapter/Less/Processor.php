<?php

namespace Baldwin\LessJsCompiler\Css\PreProcessor\Adapter\Less;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Css\PreProcessor\File\Temporary;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as Filesystem;
use Magento\Framework\Phrase;
use Magento\Framework\ShellInterface;
use Magento\Framework\View\Asset\ContentProcessorException;
use Magento\Framework\View\Asset\ContentProcessorInterface;
use Magento\Framework\View\Asset\File as AssetFile;
use Magento\Framework\View\Asset\Source;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class Processor implements ContentProcessorInterface
{
    private $logger;
    private $assetSource;
    private $temporaryFile;
    private $shell;
    private $productMetadata;
    private $filesystem;
    private $directoryList;
    private $scopeConfig;

    public function __construct(
        LoggerInterface $logger,
        Source $assetSource,
        Temporary $temporaryFile,
        ShellInterface $shell,
        ProductMetadataInterface $productMetadata,
        Filesystem $filesystem,
        DirectoryList $directoryList,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->logger = $logger;
        $this->assetSource = $assetSource;
        $this->temporaryFile = $temporaryFile;
        $this->shell = $shell;
        $this->productMetadata = $productMetadata;
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @throws ContentProcessorException
     */
    public function processContent(AssetFile $asset)
    {
        $path = $asset->getPath();
        try {
            $content = (string) $this->assetSource->getContent($asset);

            if (trim($content) === '') {
                throw new ContentProcessorException(
                    new Phrase('Compilation from source: LESS file is empty: ' . $path)
                );
            }

            $tmpFilePath = $this->temporaryFile->createFile($path, $content);

            $content = $this->compileFile($tmpFilePath, $path);

            if (trim($content) === '') {
                throw new ContentProcessorException(
                    new Phrase('Compilation from source: CSS is empty from LESS file: ' . $path)
                );
            } else {
                return $content;
            }
        } catch (\Exception $e) {
            $previousExceptionMessage = $e->getPrevious() !== null ? (PHP_EOL . $e->getPrevious()->getMessage()) : '';
            $errorMessage = $e->getMessage() . $previousExceptionMessage;

            $this->outputErrorMessage($errorMessage, $asset);
            throw new ContentProcessorException(new Phrase($errorMessage));
        }
    }

    /**
     * Compiles less file and returns output as a string
     *
     * @param string $filePath
     * @param string $assetPath
     *
     * @return string
     *
     * @throws NotFoundException if the nodejs or less compiler binaries can't be found
     * @throws LocalizedException if the shell command returns non-zero exit code
     */
    protected function compileFile($filePath, $assetPath)
    {
        $nodeCmdArgs = $this->getNodeArgsAsArray();
        $lessCmdArgs = $this->getCompilerArgsAsArray();

        $command = [];
        $command[] = $this->getPathToNodeBinary();
        $command = array_merge($command, $nodeCmdArgs);
        $command[] = $this->getPathToLessCompiler();
        $command = array_merge($command, $lessCmdArgs);
        $command[] = $filePath;

        $process = $this->getProcess($command);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $ex) {
            throw new ContentProcessorException(
                new Phrase('LESS compilation process failed with: %1', [$ex->getMessage()])
            );
        }

        $errorOutput = $process->getErrorOutput();

        if ($errorOutput !== '') {
            $errorMessage = new Phrase('LESS compilation ran into some problems: %1', [$errorOutput]);
            if ($this->isThrowOnErrorEnabled()) {
                throw new ContentProcessorException($errorMessage);
            } else {
                $this->logger->error($errorMessage, [
                    'asset' => $assetPath,
                    'file'  => $filePath,
                ]);
            }
        }

        return $process->getOutput();
    }

    /**
     * Get all arguments which will be used in the cli call to the lessc compiler
     *
     * @return string
     */
    protected function getCompilerArgsAsString()
    {
        $args = $this->getConfigValueFromPath('dev/less_js_compiler/less_arguments');
        if ($args === null) {
            // default supplied args
            $args = '--no-color'; // for example: --ie-compat --compress --math="always", ...
        }

        return $args;
    }

    /**
     * Get all arguments which will be used in the cli call to the lessc compiler
     *
     * @return array<string>
     */
    protected function getCompilerArgsAsArray()
    {
        return explode(' ', $this->getCompilerArgsAsString());
    }

    /**
     * Get the path to the lessc nodejs compiler
     *
     * @return string
     *
     * @throws NotFoundException
     */
    protected function getPathToLessCompiler()
    {
        $rootDir = $this->directoryList->getRoot();

        $lesscLocations = [
            $rootDir . '/node_modules/.bin/lessc',
            $rootDir . '/node_modules/less/bin/lessc',
        ];

        foreach ($lesscLocations as $lesscLocation) {
            if ($this->filesystem->fileExists($lesscLocation)) {
                return $lesscLocation;
            }
        }

        throw new NotFoundException(__('Less compiler not found, make sure the node package "less" is installed'));
    }

    /**
     * Get all arguments which will be used in the cli call with the nodejs binary
     *
     * @return string
     */
    protected function getNodeArgsAsString()
    {
        $args = $this->getConfigValueFromPath('dev/less_js_compiler/node_arguments');
        if ($args === null) {
            // default supplied args
            $args = '--no-deprecation'; // squelch warnings about deprecated modules being used
        }

        return $args;
    }

    /**
     * Get all arguments which will be used in the cli call with the nodejs binary
     *
     * @return array<string>
     */
    protected function getNodeArgsAsArray()
    {
        return explode(' ', $this->getNodeArgsAsString());
    }

    /**
     * Get the path to the nodejs binary
     *
     * @return string
     *
     * @throws NotFoundException
     */
    protected function getPathToNodeBinary()
    {
        $nodeJsBinary = 'node';

        try {
            $cmd = 'command -v %s';
            $nodeJsBinary = $this->shell->execute($cmd, [$nodeJsBinary]);
        } catch (LocalizedException $ex) {
            throw new NotFoundException(__(
                "Node.js binary '$nodeJsBinary' not found, " .
                'make sure it exists in the PATH of the user executing this command'
            ));
        }

        return $nodeJsBinary;
    }

    /**
     * In Magento 2.0.x and 2.1.x simply throwing a ContentProcessorException didn't output the error to a log file
     * So for those versions, we still need to output the error message ourselves to the logger
     * In Magento 2.2.x this was changed and the thrown ContentProcessorException is outputted to a log file,
     * so in those versions it already happens "automatically"
     * See MAGETWO-54937 - https://github.com/magento/magento2/commit/19ccc61e4208ce570fa040f9ccfdf972da99f7de#diff-e4bf695b706792374f33d6eca9bd9006L345
     *
     * @param string $errorMessage
     *
     * @return void
     */
    protected function outputErrorMessage($errorMessage, AssetFile $file)
    {
        $version = $this->productMetadata->getVersion();
        if (version_compare($version, '2.2.0', '>=') === true) {
            return;
        }

        $errorMessage = __('Compilation from source: ')
            . $file->getSourceFile()
            . PHP_EOL . $errorMessage;

        $this->logger->critical($errorMessage);
    }

    /**
     * @param string $path
     *
     * @return ?string
     */
    private function getConfigValueFromPath($path)
    {
        $value = $this->scopeConfig->getValue($path);
        if (is_string($value)) {
            return $value;
        }

        return null;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    private function getConfigValueFromPathAsBool($path)
    {
        return filter_var($this->scopeConfig->getValue($path), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param array<string> $commandArgs
     *
     * @return Process
     */
    private function getProcess(array $commandArgs)
    {
        // We can't use Process class in symfony/process 2.x because it takes a string and not an array
        // therefore we use ProcessBuilder, which exists only in symfony/process >= 2.1 < 4.0
        if (class_exists(ProcessBuilder::class)) {
            return (new ProcessBuilder($commandArgs))->getProcess();
        }

        return new Process($commandArgs);
    }

    /**
     * @return bool
     */
    private function isThrowOnErrorEnabled()
    {
        return $this->getConfigValueFromPathAsBool('dev/less_js_compiler/throw_on_error');
    }
}
