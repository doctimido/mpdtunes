	<meta charset="utf-8"> 
	<meta name="viewport" content="width=device-width, minimum-scale=1, maximum-scale=1, user-scalable=no">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="description" content="{{ $meta_description_content_i18n }}">
	<meta name="google-site-verification" content="{{ $google_site_verification_code }}" />

	<link type="image/png" href="/images/apple-touch-icon.png" rel="apple-touch-icon">
	<link href="/images/favicon.ico" rel="icon">
	<link href="/images/favicon.png" rel="icon" type="image/png">

	<title>{{ $site_title }}</title>

	<!--<base href="http://www.mpdtunes.com" />-->

        @if ($environment == "development")

		@if ( $theme_icon_class == "ui-icon-alt" ) {{-- determine whether to use white or black icon sets --}}
			<link rel="stylesheet" href="/includes/css/mpdtunes.theme.alt.css" />
		@else
			<link rel="stylesheet" href="/includes/css/mpdtunes.theme.css" />
		@endif

		<link rel="stylesheet" href="/includes/css/mpdtunes.main.css" />
		<link rel="stylesheet" href="/includes/css/jquery.mobile.structure.css" />
		<link rel="stylesheet" href="/includes/css/jquery.mobile.structure.overrides.css" />
		<link rel="stylesheet" href="/includes/css/mpdtunes.fileinput.enhanced.css" />
		<link rel="stylesheet" href="/includes/css/mpdtunes.player.css" />
		<link rel="stylesheet" href="/includes/css/jquery.plupload.v2.0.0-beta.queue.css" />
	    
		<script type="text/javascript" src="/includes/js/jquery-2.0.3.js"></script>
		<script type="text/javascript" src="/includes/js/jquery-ui-1.10.1.custom.js"></script>
		<script type="text/javascript" src="/includes/js/jquery.easing.1.3.js"></script>
		<script type="text/javascript" src="/includes/js/dust-core-2.0.2.js"></script>
		<script type="text/javascript" src="/includes/js/jquery.mobile.defaults.overrides.js"></script>
		<script type="text/javascript" src="/includes/js/jquery.mobile.custom-1.3.2.js"></script>
		<script type="text/javascript" src="/includes/js/jquery.mobile.lazyloader.js"></script>
 		<script type="text/javascript" src="/includes/js/fastclick.js"></script>
		<script type="text/javascript" src="/includes/js/jquery.ui.touch-punch.js"></script>
		<script type="text/javascript" src="/includes/js/jquery.jplayer.js"></script>

        @else
		{{-- There is currently an issue with minification of the CSS with yuicompressor v2.4.8 --}}
		{{-- When using the minified version of the CSS, the link back to home is broken in some browsers (eg. Safari 6.0.5 and Dolphin) --}}
		{{-- Google modpagespeed can properly optimize the css without breaking the home link --}}
		@if ( $theme_icon_class == "ui-icon-alt" ) {{-- determine whether to use white or black icon sets --}}
			<link rel="stylesheet" href="/includes/css/mpdtunes.alt.css" />
		@else
			<link rel="stylesheet" href="/includes/css/mpdtunes.css" />
		@endif

		<script type="text/javascript" src="/includes/js/jqm.cc.min.js"></script>

		<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','//www.google-analytics.com/analytics.js','ga');

			ga('create', '{{ $ga_property_id }}', 'mpdtunes.com');
			ga('send', 'pageview');
		</script>

		<!--<script type="text/javascript">
                    // Google Analytics tracking code
                    var _gaq = _gaq || [];
                    // The ga_property_id variable is set in the controller for the page being viewed.  
                    // The system wide value is set in application/config/analytics.php file which is autoloaded
                    _gaq.push(['_setAccount', '{{ $ga_property_id }}']);
                    _gaq.push(['_trackPageview']);

                    (function() {
                            var ga = document.createElement('script'); 
                            ga.type = 'text/javascript'; 
                            ga.async = true;
                            ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
                            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
                    })();
		</script>-->
        @endif
