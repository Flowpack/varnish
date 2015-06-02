MOC Varnish Neos integration
-----------------------------

.. image:: https://travis-ci.org/mocdk/MOC.Varnish.svg
   :target: https://travis-ci.org/mocdk/MOC.Varnish

This extensions provides a bridge between TYPO3 Neos and Varnish. It basically makes Neos send cache-control headers
and BAN requests to Varnish for all Document nodes.

When installed, Neos send headers for cache lifetime and cache invalidation.

=========================
Configuration
=========================

There are currently only two things that can/needs to be changed: The URL for Varnish, and the default s-maxage.
By default the Varnish cache is expected to run on 127.0.0.1

=========================
Required Varnish VCL
=========================

The extension expects Varnish to handle BAN requests with the HTTP-Headers X-Host, X-Content-Type and X-Cache-Tags.
This can be done by using the following example vcl::

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
		}
	}

Or use this for the old Varnish 3::

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
		}
	}
