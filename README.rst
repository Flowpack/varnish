MOC Varnish Neos integration
-----------------------------

This extensions provides a bridge between TYPO3 Neos and Varnish. It basically makes Neos send cache-control headers
and BAN requests to Varnish for all Document nodes.

When installed, Varnish will send two extra headers when nodes are rendered in the live-workspace. It will send
the Cache-control: s-maxage=86400 instructing shared cached (ie. Varnish) to cache this for a certain amount of time,
and it will send the header X-Neos-NodeIdentifier which is can be used for purging varnish cache for certain
node identifiers.

=========================
Configuration
=========================

There are currently only two things that can/needs to be changed: The URL for Varnish, and the default s-maxage.
By default the Varnish cache is expected to run on 127.0.0.1

=========================
Required Varnish VCL
=========================

The extension expects varnish to handle BAN requests with the http-header "X-Varnish-Ban-Neos-NodeIdentifier". This
can be done, by adding the following snippet to your vcl_recv:

::

  if (req.request == "BAN") {
  	if (req.http.Varnish-Ban-All) {
  		ban("req.url ~ / && req.http.host == " + req.http.host);
  		error 200 "Banned all";
  	}
  
  	if (req.http.X-Varnish-Ban-Neos-NodeIdentifier) {
                  ban("obj.http.X-Neos-NodeIdentifier == " + req.http.X-Varnish-Ban-Neos-NodeIdentifier);
                  error 200 "Banned Neos node identifier " + req.http.X-Varnish-Ban-Neos-NodeIdentifier;
          }
  }


You should possibly create an ACL so only certain hosts can actually ban in Varnish.
