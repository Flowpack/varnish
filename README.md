Flowpack.Varnish
===========

[![Build Status](https://travis-ci.org/flowpackdk/Flowpack.Varnish.svg?branch=master)](https://travis-ci.org/flowpackdk/Flowpack.Varnish)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/flowpackdk/Flowpack.Varnish/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/flowpackdk/Flowpack.Varnish/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/flowpack/varnish/v/stable)](https://packagist.org/packages/flowpack/varnish)
[![Total Downloads](https://poser.pugx.org/flowpack/varnish/downloads)](https://packagist.org/packages/flowpack/varnish)
[![License](https://poser.pugx.org/flowpack/varnish/license)](https://packagist.org/packages/flowpack/varnish)

Introduction
------------

This package provides a out-of-the-box seamless integration between Varnish and Neos. It basically makes Neos send
``Cache-Control`` headers and ``BAN`` requests to Varnish for all document nodes.

When installed, Neos send headers for cache lifetime and cache invalidation requests.

Compatible with Neos 1.x-5.x

Documentation
-------------

For more information, see [the documentation](Documentation/Index.rst)
