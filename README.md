# Varnish

[![Build Status](https://travis-ci.com/Flowpack/Varnish.svg?branch=master)](https://travis-ci.com/github/Flowpack/varnish)
[![Latest Stable Version](https://poser.pugx.org/flowpack/varnish/v/stable)](https://packagist.org/packages/flowpack/varnish)
[![Total Downloads](https://poser.pugx.org/flowpack/varnish/downloads)](https://packagist.org/packages/flowpack/varnish)
[![License](https://poser.pugx.org/flowpack/varnish/license)](https://packagist.org/packages/flowpack/varnish)

## Introduction

This package provides an out-of-the-box seamless integration between Varnish and Neos. It basically makes Neos send
``Cache-Control`` headers and ``BAN`` requests to Varnish for all document nodes.

### Installation

You can install the package as usual with composer:

```bash
composer require flowpack/varnish
```

When installed, Neos sends headers for cache lifetime and cache invalidation requests.

Compatible with Neos 7.x+

## Documentation

For more information, see [the documentation](Documentation/Index.rst)

## Thanks 

This package was originally developed at MOC A/S, published as MOC.Varnish and eventually moved to the Flowpack namespace. You find previous PRs and issues [there](https://github.com/mocdk/MOC.Varnish).
