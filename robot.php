<?php
set_time_limit( 0);
ob_implicit_flush( 1);
//ini_set( 'memory_limit', '4000M');
//for ( $prefix = is_dir( 'ajaxkit') ? 'ajaxkit/' : ''; ! is_dir( $prefix) && count( explode( '/', $prefix)) < 4; $prefix .= '../'); if ( ! is_file( $prefix . "env.php")) $prefix = '/web/ajaxkit/'; if ( ! is_file( $prefix . "env.php")) die( "\nERROR! Cannot find env.php in [$prefix], check your environment! (maybe you need to go to ajaxkit first?)\n\n");
//foreach ( array( 'functions', 'env') as $k) require_once( $prefix . "$k.php"); clinit(); 
require_once( 'requireme.php');
clhelp( 'PURPOSE: to process two files: SLIDES PDF and SCREEN CAPTURE MOVIE');
clhelp( 'OUTCOME: ANNOTATIONS for the movie -- times for each PDF page in the movie');
clhelp( '[pace] minimum time gap between page changes -- will ignore fickers below that threshold');
clhelp( '[wdir] full path to working directory');
clhelp( '[screen] filename of the screen capture movie -- should be in $wdir');
clhelp( '[slides] filename of the slides PDF file  -- should be in $wdir');
htg( clget( 'pace,wdir,screen,slides'));

echo "\n\n"; $e = echoeinit();
chdir( $wdir);	// move to working directory


/**
// first, manually parse output of: ffmpeg -i screen.mp4 -vf select="eq(pict_type\,I)" -vsync 0 -an frames.%03d.png
// note: changes filenames to those with timings
$lastime = null; 
`rm -Rf tempfiles.*`; `rm -Rf frames.*`;
$c = 'ffmpeg -i ' . $screen . ' -vf select="eq(pict_type\,I)" -vsync 0 -an tempfiles.%04d.png';
$in = popen( "$c 2>&1 3>&1", 'r'); echo "\n\n"; echo "cli: $c\n"; $line = '';
while ( $in && ! feof( $in)) {
	$c = fgetc( $in); if ( $c != "\n" && $c != "\r") { $line .= $c; continue; }
	$line = trim( $line); $S = $line; $line = '';
	if ( strpos( $S, 'frame=') !== 0) continue;
	$S = str_replace( "\t", '', $S); for ( $i = 0; $i < 20; $i++) $S = str_replace( '= ', '=', $S);	// get rid of spaces after equal sign
	extract( tth( $S, ' ')); // frame, time
	$ms = lpop( ttl( $time, '.')); 
	$time = ttl( lshift( ttl( $time, '.')), ':'); $time = round( ( $time[ 0] * 60 * 60 + $time[ 1] * 60 + $time[ 2]) . ".$ms", 2);
	$file = sprintf( 'tempfiles.%04d.png', $frame);
	if ( ! is_file( "$file")) die( " ERROR! Could not find [$file]\n");
	$file2 = sprintf( 'frames.%05d.%04d.png', round( $time), $frame);
	if ( $lastime && $time - $lastime < $pace) { `rm -Rf $file`; continue; } // ignore this frame, pace violation!
	`mv $file $file2`; $lastime = $time;
	echoe( $e, "frame#$frame time#$time");
}
fclose( $in); echo " OK\n\n"; `rm -Rf tempfiles*`;
die( "\n");
*/


// now, create pages from slides
$c = "pdftk $slides burst output pages.%03d.pdf"; 
echo "cli: $c\n"; system( $c); echo "\n\n";


// now, for each pages.*   in sequence  match with frames.*
echo "working pages: "; $e = echoeinit(); $e2 = echoeinit();
foreach ( ttl( '10,25,50,75,90') as $fuzz) {
	echo "\n\n"; echo "FUZZ[$fuzz]\n";
	$out = foutopen( "robot.$fuzz.bz64jsonl", 'w');
	$D = array(); $ppos = 0; $fpos = 0; $log = array(); $ppos = 1; $fpos = 0;
	lpush( $D, compact( ttl( 'ppos,fpos,log')));
	$pages = flget( '.', 'pages', '', 'pdf'); lshift( $pages);
	$frames = flget( '.', 'frames', '', 'png');
	while ( count( $pages) && $fpos < count( $frames)) {
		$page = lshift( $pages); $L = ttl( $page, '.'); lpop( $L); $proot = ltt( $L, '.');
		echoe( $e2, ''); echoe( $e, count( $pages) . " pages left   ppos#$ppos  ");
		// check if you have PNG of this page
		if ( ! is_file( "$proot.png")) echopipe( "convert -colorspace gray -bordercolor black -border 8x5 -resize 1280x720! -density 300x300 -quality 100 $proot.pdf $proot.png", null, true);
		// match frame by frame
		$h = array();
		for ( $fpos2 = 0; $fpos2 < count( $frames); $fpos2++) {
			echoe( $e2, " fpos2#$fpos2");
			$frame = $frames[ $fpos2]; $L = ttl( $frame, '.'); lpop( $L); lshift( $L); lunshift( $L, 'fitframe'); $froot = ltt( $L, '.');
			if ( ! is_file( "$froot.png")) echopipe( "convert -colorspace gray -bordercolor black -border 2x2 -trim +repage -resize 1280x720! $frame $froot.png 2>&1 3>&1", null, true);
			`rm -Rf diff.png`;
			list( $took, $metric) = echopipe( "compare -colorspace gray -metric AE -fuzz $fuzz% $proot.png $froot.png diff.png", null, true);
			if ( ! is_file( 'diff.png')) { $fpos2--; continue; } // try again
			$metric = trim( $metric);
			$in = fopen( 'diff.png', 'r'); $image = fread( $in, filesize( 'diff.png')); fclose( $in);
			$image = s2s64( $image); 	// binary format
			$h[ "$fpos2"] = compact( ttl( 'metric,image'));
		}
		$ks = hk( $h); $vs = hltl( hv( $h), 'metric'); $h2 = array(); foreach( $ks as $k => $v) $h2[ "$v"] = $vs[ $k];
		asort( $h2, SORT_NUMERIC); list( $fpos, $metric) = hfirst( $h2);
		$image = $h[ "$fpos"][ 'image']; $metrics = $vs;
		echoe( $e2, " RESULT fpos#$fpos metric#$metric"); echo "\n";
		foutwrite( $out, compact( ttl( 'ppos,fpos,metric,metrics,frames,image')));
		$ppos++;
	}
	foutclose( $out); echo "ALL DONE\n";
}


?>