# Magento 2 module which allows compiling less files using the node.js lessc compiler

## Description

This module was built out of frustration about the slow deployments of static assets to a production environment while running `bin/magento setup:static-content:deploy`. In particular this module tries to tackle the slowness which comes with compiling less files using the [less.php](https://github.com/oyejorge/less.php) library, which Magento 2 uses by default.  
This module provides a solution for using the [original less compiler](https://github.com/less/less.js) which was written in node.js  
I have [benchmarked](#benchmarks) the difference between the less.php and less.js compilers, and the less.js compiler is somewhere between two and three times as fast as the less.php compiler.

## Requirements

If you want to use this module, you'll need to be able to install [node.js](https://nodejs.org/) and [npm](https://www.npmjs.com/) on the machine(s) on which you will build your static assets.  
You'll also need to make sure that the `node` binary is available in the `$PATH` environment variable of the user which will build the static assets.  
You'll also need [composer](https://getcomposer.org/) to add this module to your Magento 2 shop.

## Installation

First, I recommend you to install the less compiler itself, and save it into your package.json file as a production dependency:

```sh
npm install --save less@1.7.5
```

Then run a shrinkwrap, so the version of less (and its dependencies) are fixed, this will produce a file `npm-shrinkwrap.json` with the exact versions of all your nodejs production dependencies and their own dependencies, so you can be sure it will use those exact versions when you install this on another machine.

```sh
npm shrinkwrap
```

> For an analogy with composer, you can compare the `package.json` file with `composer.json`, and `npm-shrinkwrap.json` with `composer.lock`

Make sure you add these files to your version control system.

> Watch out, in your deploy scripts, make sure you call `npm install --production` so you don't install all the dev dependencies

> Watch out #2: from Magento 2.1 onwards, the `package.json` file is being renamed to `package.json.sample` to enable you to have your own nodejs dependencies without Magento overwriting this every time with its own version each time you update Magento. So if you use Magento >= 2.1 make sure you copy the sample file before running the above commands.

Now install this module

```sh
composer config repositories.repo-name vcs https://github.com/baldwin-agency/magento2-module-less-js-compiler
composer require baldwin/magento2-module-less-js-compiler
```

And enable it in Magento

```sh
bin/magento module:enable Baldwin_LessJsCompiler
bin/magento setup:upgrade
```


## Remarks

1. Installing this module will effectively replace the default less compiler from Magento2. If you want to go back to the default less compiler, you need to disable this module or uninstall it.
2. I strongly recommend you to install less.js version 1.7.5, and not the very latest version (which is 2.7.1 at the time). Magento's built-in less.php library is based on less.js version 1.x and isn't compatible with 2.x. This means Magento has only tested their less files with a less compiler which is compatible with version 1.x of less. If you want to use version 2.x (which you certainly can), be aware that there might be subtle changes in the resulting css.
3. This module expects the less compiler to exist in `{MAGENTO_ROOT_DIR}/node_modules/.bin/lessc`, this is a hard coded path which is being used in the module. The compiler will end up there if you follow the installation steps above, but if for some reason you prefer to install your nodejs modules someplace else, then this module won't work. If somebody actually has this problem and has an idea how to make this path configurable, please let me know!
4. The native less processor in Magento 2 passes an option to the less compiler, which says it should compress the resulting css file (only when not in developer mode). In this module, I have chosen not to do so, as I believe this isn't a task to be executed while compiling less files. It should be done further down the line, like for example during the minification phase. If someone disagrees with this, please let me know, I'm open to discussion about this.
5. This module was tested against Magento version 2.0.7

## Benchmarks

This is by no means very professionaly conducted, but here are some tests performed on some Magento 2 shops we are working on.

| less.php  | less.js      | php    | nodejs  | themes | locales |
|:---------:|:------------:|:------:|:-------:|:------:|:-------:|
| 501984ms  | **193845ms** | 5.5.30 | 0.10.33 | 5      | 1       |
| 984101ms  | **371407ms** | 5.5.30 | 0.10.33 | 5      | 2       |
| 1124113ms | **386014ms** | 5.5.30 | 0.10.33 | 4      | 3       |

**TODO**: add some PHP 7 benchmarks
