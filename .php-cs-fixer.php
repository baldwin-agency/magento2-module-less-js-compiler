<?php

use PhpCsFixer\Finder as PhpCsFixerFinder;
use PhpCsFixer\Config as PhpCsFixerConfig;

$finder = PhpCsFixerFinder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->exclude('vendor-bin')
;

$config = new PhpCsFixerConfig();
return $config
    ->setRules([
        '@PER-CS'                                          => true,
        'binary_operator_spaces'                           => ['default' => 'at_least_single_space', 'operators' => ['=>' => 'align']],
        'declare_strict_types'                             => false, // TODO: change to true one day
        'no_alias_functions'                               => true,
        'no_unused_imports'                                => true,
        'no_useless_sprintf'                               => true,
        'nullable_type_declaration_for_default_null_value' => false, // should be 'true' when we drop support for PHP 7.0 which didn't support nullable types yet
        'ordered_imports'                                  => ['sort_algorithm' => 'alpha'],
        'operator_linebreak'                               => ['only_booleans' => true],
        'phpdoc_align'                                     => ['align' => 'left'],
        'phpdoc_separation'                                => ['skip_unlisted_annotations' => true],
        'self_accessor'                                    => true,
        'trailing_comma_in_multiline'                      => ['after_heredoc' => true, 'elements' => ['arrays']], // remove this line when we drop support for PHP < 8.0
        'visibility_required'                              => ['elements' => ['property', 'method']], // removed 'const' since we still support PHP 7.0 for now
    ])
    ->setFinder($finder)
;
