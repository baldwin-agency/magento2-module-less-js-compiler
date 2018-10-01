<?php

namespace Baldwin\LessJsCompiler\Css\PreProcessor\Adapter\Less;

use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\State;
use Magento\Framework\Css\PreProcessor\File\Temporary;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\ShellInterface;
use Magento\Framework\View\Asset\ContentProcessorException;
use Magento\Framework\View\Asset\ContentProcessorInterface;
use Magento\Framework\View\Asset\File;
use Magento\Framework\View\Asset\Source;
use Psr\Log\LoggerInterface;

/**
 * Class Processor
 */
class Processor implements ContentProcessorInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var State
     */
    private $appState;

    /**
     * @var Source
     */
    private $assetSource;

    /**
     * @var Temporary
     */
    private $temporaryFile;

    /**
     * @var ShellInterface
     */
    private $shell;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param State $appState
     * @param Source $assetSource
     * @param Temporary $temporaryFile
     * @param ShellInterface $shell
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        LoggerInterface $logger,
        State $appState,
        Source $assetSource,
        Temporary $temporaryFile,
        ShellInterface $shell,
        ProductMetadataInterface $productMetadata
    ) {
        $this->logger = $logger;
        $this->appState = $appState;
        $this->assetSource = $assetSource;
        $this->temporaryFile = $temporaryFile;
        $this->shell = $shell;
        $this->productMetadata = $productMetadata;
    }

    /**
     * @inheritdoc
     * @throws ContentProcessorException
     */
    public function processContent(File $asset)
    {
        $path = $asset->getPath();
        try {
            $content = $this->assetSource->getContent($asset);

            if (trim($content) === '') {
                return '';
            }

            $tmpFilePath = $this->temporaryFile->createFile($path, $content);

            $content = $this->compileFile($tmpFilePath);

            if (trim($content) === '') {
                $this->logger->warning('Parsed less file is empty: ' . $path);
                return '';
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
     * @return string
     * @throws \Exception
     */
    protected function compileFile($filePath)
    {
        $nodeCmdArgs = $this->getNodeArgsAsString();
        $lessCmdArgs = $this->getCompilerArgsAsString();
        $cmd = "%s $nodeCmdArgs %s $lessCmdArgs %s";

        // to log or not to log, that's the question
        // also, it would be better to use the logger in the Shell class,
        // since that one will contain the exact correct command, and not this sprintf version
        // $this->logger->debug('Less compilation command: `'
        //     . sprintf($cmd, $this->getPathToNodeBinary(), $this->getPathToLessCompiler(), $filePath)
        //     . '`');

        return $this->shell->execute($cmd,
            [
                $this->getPathToNodeBinary(),
                $this->getPathToLessCompiler(),
                $filePath,
            ]
        );
    }

    /**
     * Get all arguments which will be used in the cli call to the lessc compiler
     *
     * @return string
     */
    protected function getCompilerArgsAsString()
    {
        $args = ['--no-color']; // for example: --no-ie-compat, --no-js, --compress, ...

        return implode(' ', $args);
    }

    /**
     * Get the path to the lessc nodejs compiler
     *
     * @return string
     * @throws \Exception
     */
    protected function getPathToLessCompiler()
    {
        $lesscLocations = [
            BP . '/node_modules/.bin/lessc',
            BP . '/node_modules/less/bin/lessc',
        ];

        foreach ($lesscLocations as $lesscLocation) {
            if (file_exists($lesscLocation)) {
                return $lesscLocation;
            }
        }

        throw new \Exception('Less compiler not found, make sure the node package "less" is installed');
    }

    /**
     * Get all arguments which will be used in the cli call with the nodejs binary
     *
     * @return string
     */
    protected function getNodeArgsAsString()
    {
        $args = ['--no-deprecation']; // squelch warnings about deprecated modules being used

        return implode(' ', $args);
    }

    /**
     * Get the path to the nodejs binary
     *
     * @return string
     * @throws \Exception
     */
    protected function getPathToNodeBinary()
    {
        $nodeJsBinary = 'node';

        try {
            $cmd = 'command -v %s';
            $nodeJsBinary = $this->shell->execute($cmd, [$nodeJsBinary]);
        } catch (LocalizedException $ex) {
            throw new \Exception("Node.js binary '$nodeJsBinary' not found, make sure it exists in the PATH of the user executing this command");
        }

        return $nodeJsBinary;
    }

    /**
     * In Magento 2.0.x and 2.1.x simply throwing a ContentProcessorException didn't output the error to a log file
     * So for those versions, we still need to output the error message ourselves to the logger
     * In Magento 2.2.x this was changed and the thrown ContentProcessorException is outputted to a log file, so in those versions it already happens "automatically"
     * See MAGETWO-54937 - https://github.com/magento/magento2/commit/19ccc61e4208ce570fa040f9ccfdf972da99f7de#diff-e4bf695b706792374f33d6eca9bd9006L345
     *
     * @param string $errorMessage
     * @param File $file
     */
    protected function outputErrorMessage($errorMessage, File $file)
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
}
