<?php

namespace Baldwin\LessJsCompiler\Css\PreProcessor\Adapter\Less;

use Magento\Framework\App\State;
use Magento\Framework\Css\PreProcessor\File\Temporary;
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
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param State $appState
     * @param Source $assetSource
     * @param Temporary $temporaryFile
     * @param ShellInterface $shell
     */
    public function __construct(
        LoggerInterface $logger,
        State $appState,
        Source $assetSource,
        Temporary $temporaryFile,
        ShellInterface $shell
    ) {
        $this->logger = $logger;
        $this->appState = $appState;
        $this->assetSource = $assetSource;
        $this->temporaryFile = $temporaryFile;
        $this->shell = $shell;
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

            throw new ContentProcessorException(new Phrase($errorMessage));
        }
    }

    /**
     * Compiles less file and returns output as a string
     *
     * @param string $filePath
     * @return string
     */
    protected function compileFile($filePath)
    {
        $cmdArgs = $this->getCompilerArgsAsString();
        $cmd = "%s $cmdArgs %s";

        // to log or not to log, that's the question
        // also, it would be better to use the logger in the Shell class,
        // since that one will contain the exact correct command, and not this sprintf version
        // $this->logger->debug('Less compilation command: `'
        //     . sprintf($cmd, $this->getPathToLessCompiler(), $filePath)
        //     . '`');

        return $this->shell->execute($cmd,
            [
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
     */
    protected function getPathToLessCompiler()
    {
        return BP . '/node_modules/.bin/lessc';
    }
}
