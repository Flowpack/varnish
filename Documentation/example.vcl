backend default {
	.host = "127.0.0.1";
	.port = "8080";
}

backend admin {
	.host = "127.0.0.1";
	.port = "80";
	.connect_timeout = 600s;
	.first_byte_timeout = 600s;
	.between_bytes_timeout = 600s;
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
		} else if (req.http.X-Site) {
			ban("obj.http.X-Host ~ " + req.http.X-Host
				+ " && obj.http.X-Url ~ " + req.http.X-Url
				+ " && obj.http.content-type ~ " + req.http.X-Content-Type
				+ " && obj.http.X-Site ~ " + req.http.X-Site
			);
		}

		error 200 "Banned";
	}

	if (req.request == "PURGE") {
		if (!client.ip ~ purge) {
			error 405 "Not allowed.";
		}

		ban("req.url ~ " + req.url + "$ && req.http.host == " + req.http.host);
		error 200 "Purged.";
	}

	# Add a unique header containing the client address, or append to existing header
	if (req.http.X-Forwarded-For) {
		set req.http.X-Forwarded-For = req.http.X-Forwarded-For + ", " + client.ip;
	} else {
		set req.http.X-Forwarded-For = client.ip;
	}

	if (req.http.X-Tx-Solr-Iq) {
		return(pipe);
	}

	if (req.backend.healthy) {
		set req.grace = 0s;
	} else {
		set req.grace = 24h;
	}

	if (req.request != "GET" &&
		req.request != "HEAD" &&
		req.request != "PUT" &&
		req.request != "POST" &&
		req.request != "TRACE" &&
		req.request != "OPTIONS" &&
		req.request != "DELETE") {
		/* Non-RFC2616 or CONNECT which is weird. */
		return (pipe);
	}

	if (req.request != "GET" && req.request != "HEAD") {
		/* We only deal with GET and HEAD by default */
		return (pass);
	}

	# Always cache all static files
	if (req.url ~ "\.(png|gif|jpg|bmp|webm|svg|swf|js|css|woff|woff2|eot|ttc|ttf|otf|f4a|f4b|m4a|oga|ogg|opus|webp|f4v|f4p|m4v|mp4|ogv|flv|cur|ico|vcard|vcf|htc)$") {
		return(lookup);
	}

	# don't cache pages when logged in (apache authorization)
	if (req.http.Authorization) {
		return (pass);
	}

	if (req.url ~ "/neos/") {
		return (pass);
	}

	# mp4 streaming must be in pipe to work with I-devices
	if (req.url ~ "\.mp4$"){
		return(pipe);
	}

	return(lookup);
}

sub vcl_fetch {
	if (beresp.http.Cache-Control ~ "max-age" || beresp.http.Cache-Control ~ "s-maxage") {
		# Do whatever is the default behaviour
	} else {
		set beresp.ttl = 0s;
		set beresp.http.X-Cacheable = "No max age from server";
	}

	# Allow 24 hour stale content, before an error 500/404 is thrown
	set beresp.grace = 0s;

	# Respect force-reload, and clear cache accordingly. This means that a ctrl-reload will actually purge
	# the cache for this URL.
	if (req.http.Cache-Control ~ "no-cache") {
		set beresp.ttl = 0s;
		# Make sure ESI includes are processed!
		set beresp.do_esi = true;
		set beresp.http.X-Cacheable = "NO:force-reload";
		# Make sure that We remove alle cache headers, so the Browser does not cache it for us!
		remove beresp.http.Cache-Control;
		remove beresp.http.Expires;
		remove beresp.http.Last-Modified;
		remove beresp.http.ETag;
		remove beresp.http.Pragma;

		return (deliver);
	}

	if (req.url ~ "\.(png|gif|jpg|bmp|webm|svg|swf|js|css|woff|woff2|eot|ttc|ttf|otf|f4a|f4b|m4a|oga|ogg|opus|webp|f4v|f4p|m4v|mp4|ogv|flv|cur|ico|vcard|vcf|htc)$") {
		unset beresp.http.set-cookie;
		set beresp.http.X-Cacheable = "YES: png|gif|jpg|bmp|webm|svg|swf|js|css|woff|woff2|eot|ttc|ttf|otf|f4a|f4b|m4a|oga|ogg|opus|webp|f4v|f4p|m4v|mp4|ogv|flv|cur|ico|vcard|vcf|htc are always cached";
		return (deliver);
	}

	# Allow edgeside includes
	set beresp.do_esi = true;

	if (beresp.http.Set-Cookie) {
		set beresp.http.X-Cacheable = "NO: Backend sets cookie";
		# call reset_cache_headers;
		# Set the ttl for hit_for_pass objects low for this case, otherwise a backenduser having the first
		# hit will make Varnish cache the "uncacheability of the page
		set beresp.ttl = 0s;
		return (hit_for_pass);
	}

	# Since we rely on the CMS to send the correct cache-control headers, we do nothing except for removing the cache-control headers before output

	# Make sure that We remove all cache headers, so the Browser does not cache it for us!
	remove beresp.http.Cache-Control;
	remove beresp.http.Expires;
	remove beresp.http.Last-Modified;
	remove beresp.http.ETag;
	remove beresp.http.Pragma;

	set beresp.http.X-Cacheable = "NO";
	if (beresp.ttl > 0s) {
		set beresp.http.X-Cacheable = "YES";
	}

	# Set ban-lurker friendly custom headers
	set beresp.http.X-Url = req.url;
	set beresp.http.X-Host = req.http.host;
	set beresp.http.X-Cache-TTL = beresp.ttl;

	return (deliver);
}

sub vcl_pipe {
	# Note that only the first request to the backend will have
	# X-Forwarded-For set. If you use X-Forwarded-For and want to
	# have it set for all requests, make sure to have:
	# set req.http.connection = "close";
	# here. It is not set by default as it might break some broken web
	# applications, like IIS with NTLM authentication.
	return (pipe);
}

sub vcl_hit {
	# General hit vcl
	if (req.http.Cache-Control ~ "no-cache") {
		set obj.ttl = 0s;
		return (pass);
	}
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