Varnish integration for Neos
----------------------------

This package provides a out-of-the-box seamless integration between Varnish and Neos. It basically makes Neos send
``Cache-Control`` headers and ``BAN`` requests to Varnish for all document nodes.

When installed, Neos send headers for cache lifetime and cache invalidation requests.

=========================
Configuration
=========================

There are several configuration options can/needs to be set:

- The URL for the Varnish proxy/proxies (By default the Varnish cache is expected to run on ``http://127.0.0.1``)
   ``MOC.Varnish.varnishUrl`` allows string or array with URLs (skip trailing slash)
- Cache header for default shared maximum age (``smax-age``) - default cache and maximum cache TTL, e.g. 86400 for 24 h
   ``MOC.Varnish.cacheHeaders.defaultSharedMaximumAge`` accepts integers (seconds) - (defaults to ``NULL``)
   If not set, the Varnish configuration needs to cache by default since no ``Cache-Control`` header will be sent
- Disable sending of cache headers - can be used to disable Varnish on staging environment e.g.
   ``MOC.Varnish.cacheHeaders.disabled`` accepts boolean value (defaults to ``FALSE``)
- Reverse lookup port can be set to allow debugging in the backend module if the web server port is not ``80``
   ``MOC.Varnish.reverseLookupPort`` accepts integer (defaults to ``NULL``)
- Ignored cache tags can be used to ignore certain cache tags from being cleared at all (useful for optimizing)
   ``MOC.Varnish.ignoredCacheTags`` accepts array of strings (defaults to ``NULL``)
   E.g. 'TYPO3.Neos:Document' which is used in 'TYPO3.Neos:Menu' elements

=========================
How it works
=========================

Varnish works by caching HTTP requests based on their ``Cache-Control`` headers and every url is a unique cache entry.

This package works by integrating with the TypoScript Content Cache in Neos for sending proper ``Cache-Control`` headers in
the HTTP response for document nodes. Connected by a signal it checks if the controller is the NodeController and the
node is in the ``live`` workspace. Additionally checks for node being caching being disabled for the node using the
``disableVarnishCache`` (automatically added to node properties for document nodes types) property and sending cache
headers haven't been disabled. A ``Time-to-live`` property is also available, making it possible to set a custom TTL for
a specific document node.

***Note*** The Varnish cache is only enabled for the ``live`` workspace.

If the document node is can be cached (no uncached segments) it will add a ``Cache-Control`` header as well as two custom
headers ``X-Site`` and ``X-Cache-Tags``. The ``X-Site`` header is auto-generated and used to separate installations and
the ``X-Cache-Tags`` contains all tags used on the page, needed for cache clearing. The tags are collected by replacing
the TypoScript Content Cache frontend with a custom one, that allows for retrieving the meta data (tags) for existing
entries. This is taken being done in the ``CacheControlService``. If a uncached segment needs to be ignored for determining
if a page can be cached, the ``mocVarnishIgnoreUncached`` variable can be used in the TypoScript cache configuration.
This can be useful in case of a uncached segment that depends on ``GET`` parameters, which means the URL is different
thus a separate Varnish cache entry, but the page is mostly identical except for the segment. Alternatively this can
be solved by adding the ``GET`` parameter to the cache ``entryIdentifier``.

Example::

  @cache {
  	mode = 'uncached'
  	context {
  		1 = 'documentNode'
  	}
  	mocVarnishIgnoreUncached = true
  }

***Note*** For a page to be cached, it must not contains any uncached parts (e.g. plugins which are uncachable by default).

When a node is published to the ``live`` workspace a ban request is send to the
Varnish proxy with the node's cache tags. This is done by listening to the ``nodePublished`` event from the
``PublishingService`` which calls ``flushForNode`` in ``ContentCacheFlusherService``. For custom flushing of the cache,
e.g. on node import, either use ``flushForNode`` or alternatively ``flushForNodeData`` if working directly with NodeData.
The ``ContentCacheFlusherService`` generates cache tags for all published nodes and during shutdown it will send one ban
request, using the ``VarnishBanService``, containing all the tags to be cleared. The ``VarnishBanService`` has two methods
``banAll`` accepting ``domain`` & ``contentType`` (MIME type) and ``banByTags`` accepting ``tags`` and ``domain``.

The package supports content dimensions as long as they are part of the URL.

The package uses the FriendsOfSymfony package FOSHttpCache_ for sending requests to the Varnish proxy/proxies and
lends inspiration for how to interact with Varnish. More information can be found in it's documentation_.

.. _FOSHttpCache: https://github.com/FriendsOfSymfony/FOSHttpCache

.. _documentation: http://foshttpcache.readthedocs.org/en/stable/varnish-configuration.html

=========================
Debugging
=========================

For Varnish to cache pages correctly, the additional headers need to be available when requesting the page directly from
the server. These include ``X-Site`` & ``X-Cache-Tags``. If those headers aren't present the page won't be
cached. This is likely due to the page not being cacheable or headers being disabled.

Enable debug logging by changing the configuration setting ``MOC.Varnish.log.backendOptions.severityThreshold`` to '%LOG_DEBUG%'

Also make sure the setting ``MOC.Varnish.cacheHeaders.disabled`` is not enabled.

=========================
Command
=========================

A command for clearing the Varnish cache is available with ``./flow varnish:clear``, which accepts two optional
parameters ``domain`` and ``contentType`` (MIME type).

=========================
Backend module
=========================

To make controller and debugging the Varnish cache proxy easier, a Neos backend module is available. It allows for
clearing cache all cache for the site with an optional content-type filter. Additionally allows for clearing cache by
certain tags. Lastly it allows for searching for individual document nodes for clearing cache and fetching cache
information for each of the found nodes. The module is accessible to

Additionally the configuration options are visible.

  .. figure:: Images/VarnishBackendModuleCacheClearing.jpg
:alt: Screenshot of cache clearing in Neos Backend Module

  .. figure:: Images/VarnishBackendModuleSearch.jpg
:alt: Screenshot of node search in Neos Backend Module

=========================
Shared Varnish support
=========================

A unique token for every Flow installation is generated if one doesn't already exist. This is used to separate cache
entries in Varnish for every installation to only clear for the correct one. This token is located in
``Data/Persistent/MocVarnishSiteToken/VarnishSiteToken`` and can be copied to keep across installations.

=========================
Multi-site support
=========================

When having multiple sites the cache entries in Varnish are separated by only clearing for the first active domain for a
site. This prevents clearing cache for all sites in a installation.

***Note*** Make sure the first active domain is the primary one.

=========================
Required Varnish VCL
=========================

The package expects Varnish to handle BAN requests with the HTTP-Headers ``X-Host``, ``X-Content-Type`` and ``X-Cache-Tags``.
This can be done by using the following example vcl:

*Varnish 4*::

	vcl 4.0;
	backend default {
		.host = "127.0.0.1";
		.port = "8080";
	}

	acl invalidators {
		"127.0.0.1";
	}

	sub vcl_recv {
		if (req.method == "BAN") {
			if (!client.ip ~ invalidators) {
				return (synth(405, "Not allowed"));
			}

			if (req.http.X-Cache-Tags) {
				ban("obj.http.X-Host ~ " + req.http.X-Host
					+ " && obj.http.X-Url ~ " + req.http.X-Url
					+ " && obj.http.content-type ~ " + req.http.X-Content-Type
					+ " && obj.http.X-Cache-Tags ~ " + req.http.X-Cache-Tags
					+ " && obj.http.X-Site ~ " + req.http.X-Site
				);
			} else {
				ban("obj.http.X-Host ~ " + req.http.X-Host
					+ " && obj.http.X-Url ~ " + req.http.X-Url
					+ " && obj.http.content-type ~ " + req.http.X-Content-Type
					+ " && obj.http.X-Site ~ " + req.http.X-Site
				);
			}

			return (synth(200, "Banned"));
		}
	}

	sub vcl_backend_response {
		# Set ban-lurker friendly custom headers
		set beresp.http.X-Url = bereq.url;
		set beresp.http.X-Host = bereq.http.host;
		set beresp.http.X-Cache-TTL = beresp.ttl;
	}

	sub vcl_deliver {
		# Send debug headers if a X-Cache-Debug header is present from the client or the backend
		if (req.http.X-Cache-Debug || resp.http.X-Cache-Debug) {
			if (resp.http.X-Varnish ~ " ") {
				set resp.http.X-Cache = "HIT";
			} else {
				set resp.http.X-Cache = "MISS";
			}
		} else {
			# Remove ban-lurker friendly custom headers when delivering to client
			unset resp.http.X-Url;
			unset resp.http.X-Host;
			unset resp.http.X-Cache-Tags;
			unset resp.http.X-Site;
			unset resp.http.X-Cache-TTL;
		}
	}

*Varnish 3*::

	backend default {
		.host = "127.0.0.1";
		.port = "8080";
	}

	acl invalidators {
		"127.0.0.1";
	}

	sub vcl_recv {
		if (req.request == "BAN") {
			if (!client.ip ~ invalidators) {
				error 405 "Not allowed.";
			}

			if (req.http.X-Cache-Tags) {
				ban("obj.http.X-Host ~ " + req.http.X-Host
					+ " && obj.http.X-Url ~ " + req.http.X-Url
					+ " && obj.http.content-type ~ " + req.http.X-Content-Type
					+ " && obj.http.X-Cache-Tags ~ " + req.http.X-Cache-Tags
					+ " && obj.http.X-Site ~ " + req.http.X-Site
				);
			} else {
				ban("obj.http.X-Host ~ " + req.http.X-Host
					+ " && obj.http.X-Url ~ " + req.http.X-Url
					+ " && obj.http.content-type ~ " + req.http.X-Content-Type
					+ " && obj.http.X-Site ~ " + req.http.X-Site
				);
			}

			error 200 "Banned";
		}
	}

	sub vcl_fetch {
		# Set ban-lurker friendly custom headers
		set beresp.http.X-Url = req.url;
		set beresp.http.X-Host = req.http.host;
		set beresp.http.X-Cache-TTL = beresp.ttl;
	}

	sub vcl_deliver {
		# Send debug headers if a X-Cache-Debug header is present from the client or the backend
		if (req.http.X-Cache-Debug || resp.http.X-Cache-Debug) {
			if (obj.hits > 0) {
				set resp.http.X-Cache = "HIT";
			} else {
				set resp.http.X-Cache = "MISS";
			}
		} else {
			# Remove ban-lurker friendly custom headers when delivering to client
			unset resp.http.X-Url;
			unset resp.http.X-Host;
			unset resp.http.X-Cache-Tags;
			unset resp.http.X-Site;
			unset resp.http.X-Cache-TTL;
		}
	}

***Note*** Example_ of full VCL configuration file (Varnish 3) – Use with care!

.. _Example: https://github.com/mocdk/MOC.Varnish/blob/master/Documentation/example.vcl