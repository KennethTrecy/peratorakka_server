<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude(["vendor"])
    ->notPath("*");

return (new PhpCsFixer\Config())->setRules([
        "@PSR12" => true,
        "single_quote" => false,
        "single_line_after_imports" => true,
        "blank_line_between_import_groups" => true,
        "ordered_imports" => [
            "sort_algorithm" => "alpha",
            "case_sensitive" => true
        ],
    ])
    ->setLineEnding("\n")
    ->setFinder($finder);
