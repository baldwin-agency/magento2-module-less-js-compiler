# Magento 2 module which allows compiling less files using the less.js compiler

## Description

This module was built out of frustration about the slow deployments of static assets to a production environment while running `bin/magento setup:static-content:deploy`. In particular this module tries to tackle the slowness which comes with compiling less files using the [less.php](https://github.com/oyejorge/less.php) library, which Magento 2 uses by default.  
This module provides a solution by using the [original less.js compiler](https://github.com/less/less.js) which was written in javascript and is executed through node.js  
We have [benchmarked](#benchmarks) the difference between the less.php and less.js compilers, and the less.js compiler is somewhere between 1.5 and 3 times as fast as the less.php compiler, although it depends on your PHP version. If you run PHP 5.x the performance increase will be much higher, PHP 7.x is actually quite fast by itself, but the nodejs version still beats it.

**Update**: Since Magento 2.3.0, it seems like the performance differences between less.php and less.js are not very big anymore. I have the suspicion this might have something to do with newer [releases of the less.php](https://github.com/oyejorge/less.php/releases) library. Which got released somewhere around the period when Magento 2.2.3 & 2.1.12 were released. So it's possible that the performance difference for Magento 2.2.x and 2.1.x might also be less significant then how it's displayed [below](#benchmarks) if you upgrade the less.php library to the latest version (v1.7.0.14 at the time of writing).  
This is a bit of speculation, since unfortunately I didn't keep track of the version of the less.php library which I've used in the benchmarks, so I'm not sure about this statement.

## Requirements

You'll need at least Magento 2.0.7. We didn't bother with testing this module on older versions.  
If you want to use this module, you'll need to be able to install [node.js](https://nodejs.org/) and [npm](https://www.npmjs.com/) on the machine(s) on which you will build your static assets.  
You'll also need to make sure that the `node` binary is available in the `$PATH` environment variable of the user which will build the static assets.  
You'll also need [composer](https://getcomposer.org/) to add this module to your Magento 2 shop.

## Installation

First, we recommend you to install the less compiler itself, and save it into your `package.json` file as a production dependency:

```sh
npm install --save less@1.7.5
```

> Watch out: from Magento 2.1 onwards, the `package.json` file is being renamed to `package.json.sample` to enable you to have your own nodejs dependencies without Magento overwriting this every time with its own version each time you update Magento. So if you use Magento >= 2.1 make sure you copy the `package.json.sample` file to `package.json` before running the above command if you want to work with Magento's `grunt` setup.

Then run a shrinkwrap, so the version of less (and its dependencies) are fixed, this will produce a file `npm-shrinkwrap.json` with the exact versions of all your nodejs production dependencies and their own dependencies, so you can be sure it will use those exact versions when you install this on another machine.

```sh
npm shrinkwrap
```

And make sure you add these two files to your version control system.

> For an analogy with composer, you can compare the `package.json` file with `composer.json`, and `npm-shrinkwrap.json` with `composer.lock`

Now install this module

```sh
composer require baldwin/magento2-module-less-js-compiler
```

And enable it in Magento

```sh
bin/magento module:enable Baldwin_LessJsCompiler
bin/magento setup:upgrade
```

As the last step, in your deploy scripts, make sure you call `npm install --production`. You should execute this somewhere between `composer install` and `bin/magento setup:static-content:deploy`

## Debugging less compilation errors

When your `.less` files have a syntax error or contain something which doesn't allow it to compile properly, please have a look at the `var/log/system.log` file, it will contain the error what causes the problem.

## Remarks

1. Installing this module will effectively replace the default less compiler from Magento2. If you want to go back to the default less compiler, you need to disable this module or uninstall it.
2. We strongly recommend you to install less.js version 1.7.5, and not the very latest version (which is 2.7.1 at the time). Magento's built-in less.php library is based on less.js version 1.x and isn't compatible with 2.x. This means Magento has only tested their less files with a less compiler which is compatible with version 1.x of less. If you want to use version 2.x (which you certainly can), be aware that there might be subtle changes in the resulting css.  
Also: if you use `grunt` or `gulp` in your frontend workflow, you are probably also using less.js version 1.7.5, so using this module makes sure what you see on the server is 100% exactly the same as on your developer machine.
3. This module expects the less compiler to exist in `{MAGENTO_ROOT_DIR}/node_modules/.bin/lessc`, this is a hard coded path which is being used in the module. The compiler will end up there if you follow the installation steps above, but if for some reason you prefer to install your nodejs modules someplace else, then this module won't work. If somebody actually has this problem and has an idea how to make this path configurable (preferably without getting it from the database), please let me know!
4. The default less processor in Magento 2 passes an option to the less compiler, which says it should [compress the resulting css file](https://github.com/magento/magento2/blob/6a40b41f6281c7d405cd78029d6becab1d837c87/lib/internal/Magento/Framework/Css/PreProcessor/Adapter/Less/Processor.php#L73). In this module, we have chosen not to do so, as we believe this isn't a task to be executed while compiling less files. It should be done further down the line, like for example during the minification phase. If someone disagrees with this, please let me know, I'm open to discussion about this.
5. This module was tested against Magento versions 2.0.7, 2.1.0 - 2.1.9 and 2.2.0

## Benchmarks

### Intro

This is by no means very professionaly conducted, but here are some tests performed on some Magento 2 shops we are working on.  
We disabled parallelism to make the comparison between different Magento versions easier to understand.  
We only measured the duration of the `bin/magento setup:static-content:deploy` command, xdebug is disabled as it causes a massive slowdown, and before every run we make sure all caches are clean, by running:

```sh
rm -R pub/static/* var/cache/* var/view_preprocessed/* var/generation/* var/di/* var/page_cache/* generated/*
```

### Machines

- The _older_ server is a server which is in constant use and has older software installed on it.  
- The _newer_ server is a new server which currently receives no traffic and has al the sparkly new software versions installed (at the time of writing).  
- The _older-local_ machine is a 2011 Macbook Pro (HDD has been upgraded to SSD, no vagrant or docker, just native software using Macports)  
- The _newer-local_ machine is a 2017 Macbook Pro (native software using Homebrew or Macports)

### Results

| magento   | themes | locales | strategy | machine     | php    | nodejs  | less.php  | less.js   |
|:---------:|:------:|:-------:|:--------:|:-----------:|:------:|:-------:|:---------:|:---------:|
| 2.0.7     | 5      | 1       | -        | older       | 5.5.30 | 0.10.33 | 8m22s     | **3m14s** |
| 2.0.7     | 5      | 2       | -        | older       | 5.5.30 | 0.10.33 | 16m24s    | **6m11s** |
| 2.0.7     | 4      | 3       | -        | older       | 5.5.30 | 0.10.33 | 18m44s    | **6m26s** |
| 2.0.7     | 5      | 1       | -        | newer       | 7.0.7  | 4.2.6   | 1m30s     | **1m00s** |
| 2.0.7     | 5      | 2       | -        | newer       | 7.0.7  | 4.2.6   | 3m06s     | **1m51s** |
| 2.0.7     | 5      | 3       | -        | newer       | 7.0.7  | 4.2.6   | 4m52s     | **2m52s** |
| 2.1.0-rc1 | 3      | 1       | -        | older-local | 5.5.36 | 4.4.3   | 4m39s     | **2m01s** |
| 2.1.0-rc1 | 3      | 1       | -        | older-local | 5.6.22 | 4.4.3   | 4m17s     | **2m02s** |
| 2.1.0-rc1 | 3      | 1       | -        | older-local | 7.0.7  | 4.4.3   | 2m01s     | **1m26s** |
| 2.1.9     | 3      | 1       | -        | newer-local | 7.0.23 | 4.8.4   | 2m35s     | **1m14s** |
| 2.1.9     | 3      | 2       | -        | newer-local | 7.0.23 | 4.8.4   | 2m44s     | **1m05s** |
| 2.2.0     | 3      | 1       | standard | newer-local | 7.0.23 | 4.8.4   | 1m42s     | **0m38s** |
| 2.2.0     | 3      | 1       | quick*   | newer-local | 7.0.23 | 4.8.4   | 1m42s     | **0m38s** |
| 2.2.0     | 3      | 1       | compact  | newer-local | 7.0.23 | 4.8.4   | 1m42s     | **0m38s** |
| 2.2.0     | 3      | 2       | standard | newer-local | 7.0.23 | 4.8.4   | 3m30s     | **1m05s** |
| 2.2.0     | 3      | 2       | quick*   | newer-local | 7.0.23 | 4.8.4   | 3m29s     | **1m07s** |
| 2.2.0     | 3      | 2       | compact  | newer-local | 7.0.23 | 4.8.4   | 1m52s     | **0m40s** |
| 2.3.0     | 3      | 2       | standard | newer-local | 7.2.12 | 8.12.0  | 1m35s     | **1m26s** |
| 2.3.0     | 3      | 2       | quick*   | newer-local | 7.2.12 | 8.12.0  | 1m35s     | **1m28s** |
| 2.3.0     | 3      | 2       | compact  | newer-local | 7.2.12 | 8.12.0  | 0m43s     | **0m42s** |


_*_ The [quick strategy](http://devdocs.magento.com/guides/v2.2/config-guide/cli/config-cli-subcommands-static-deploy-strategies.html) deployment is [currently bugged in Magento 2.2.x and 2.3.x](https://github.com/magento/magento2/issues/10674) and behaves the same as the standard strategy
