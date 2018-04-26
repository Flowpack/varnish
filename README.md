MOC.Varnish
===========

[![Build Status](https://travis-ci.org/mocdk/MOC.Varnish.svg?branch=master)](https://travis-ci.org/mocdk/MOC.Varnish)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/mocdk/MOC.Varnish/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/mocdk/MOC.Varnish/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/moc/varnish/v/stable)](https://packagist.org/packages/moc/varnish)
[![Total Downloads](https://poser.pugx.org/moc/varnish/downloads)](https://packagist.org/packages/moc/varnish)
[![License](https://poser.pugx.org/moc/varnish/license)](https://packagist.org/packages/moc/varnish)

Introduction
------------

This package provides a out-of-the-box seamless integration between Varnish and Neos. It basically makes Neos send
``Cache-Control`` headers and ``BAN`` requests to Varnish for all document nodes.

When installed, Neos send headers for cache lifetime and cache invalidation requests.

Compatible with Neos 1.x-4.x

Documentation
-------------

For more information, see [the documentation](Documentation/Index.rst)
