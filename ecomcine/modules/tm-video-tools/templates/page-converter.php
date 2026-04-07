<?php
/**
 * Video Converter page template.
 *
 * Standalone full-page layout — suppresses site header so we can control
 * the full viewport. Outputs a minimal shell with the converter UI container.
 *
 * @package EcomCine / TM Video Tools
 */

defined( 'ABSPATH' ) || exit;

// COOP + COEP are required so the browser sets crossOriginIsolated=true,
// which enables SharedArrayBuffer for the multi-threaded ffmpeg.wasm build.
header( 'Cross-Origin-Opener-Policy: same-origin' );
header( 'Cross-Origin-Embedder-Policy: require-corp' );

$GLOBALS['ecomcine_suppress_site_header'] = true;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — WebM Video Converter</title>
	<?php wp_head(); ?>
</head>
<body class="tm-converter-page">

<div id="tm-converter-root">

	<!-- Hero -->
	<section class="tvc-hero">
		<h1 class="tvc-hero__title">Convert Video to WebM</h1>
		<p class="tvc-hero__sub">100% in your browser &mdash; your files never leave your computer.</p>
	</section>

	<!-- FFmpeg download banner (hidden until load starts) -->
	<div id="tvc-ffmpeg-loading" class="tvc-ffmpeg-banner" aria-live="polite" hidden>
		<div class="tvc-ffmpeg-banner__inner">
			<div class="tvc-ffmpeg-banner__text">
				<strong>Loading FFmpeg&hellip;</strong>
				<span id="tvc-ffmpeg-status">Fetching WebAssembly engine (~30 MB) &mdash; one-time download, cached afterwards.</span>
			</div>
			<div class="tvc-ffmpeg-bar-wrap">
				<div id="tvc-ffmpeg-bar" class="tvc-ffmpeg-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%"></div>
			</div>
			<span id="tvc-ffmpeg-pct" class="tvc-ffmpeg-pct">0%</span>
		</div>
	</div>

	<!-- Main converter card (fixed min-height — two mutually exclusive views swap inside) -->
	<main class="tvc-card" id="tvc-main">

		<!-- VIEW: Convert (default) -->
		<div id="tvc-view-convert" class="tvc-view tvc-view--active">

			<!-- Drop zone -->
			<div id="tvc-dropzone" class="tvc-dropzone" tabindex="0" role="button" aria-label="Drop MP4 here or click to browse">
				<svg class="tvc-dropzone__icon" viewBox="0 0 48 48" fill="none" aria-hidden="true">
					<path d="M24 4v28M14 22l10 10 10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
					<path d="M8 36h32" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
				</svg>
				<p class="tvc-dropzone__label">Drop your MP4 here<br><span>or click to browse</span></p>
				<p id="tvc-size-limit" class="tvc-dropzone__limit"></p>
				<input type="file" id="tvc-file-input" accept="video/mp4,video/*" class="tvc-dropzone__input" aria-label="Choose video file">
			</div>

			<!-- File info (hidden until a file is chosen) -->
			<div id="tvc-file-info" class="tvc-file-info" hidden>
				<span id="tvc-file-name" class="tvc-file-info__name"></span>
				<span id="tvc-file-size" class="tvc-file-info__size"></span>
				<button id="tvc-file-clear" class="tvc-btn tvc-btn--ghost tvc-btn--sm" type="button">✕ Remove</button>
			</div>

			<!-- Quality presets (Lossless and Custom removed) -->
			<div class="tvc-quality-section">
				<label class="tvc-section-label">Quality Preset</label>
				<div class="tvc-presets" role="radiogroup" aria-label="Quality preset">
					<label class="tvc-preset">
						<input type="radio" name="tvc-quality" value="hq">
						<span class="tvc-preset__box">
							<strong>HQ Archive</strong>
							<small>CRF 18 &mdash; near-lossless, excellent for archiving</small>
						</span>
					</label>
					<label class="tvc-preset tvc-preset--default">
						<input type="radio" name="tvc-quality" value="balanced" checked>
						<span class="tvc-preset__box">
							<strong>Balanced</strong>
							<small>CRF 28 &mdash; great quality, 40&ndash;60% smaller than source</small>
						</span>
					</label>
					<label class="tvc-preset">
						<input type="radio" name="tvc-quality" value="compressed">
						<span class="tvc-preset__box">
							<strong>Compressed</strong>
							<small>CRF 40 &mdash; smaller file, some quality loss, fast</small>
						</span>
					</label>
				</div>
				<div id="tvc-custom-crf-wrap" class="tvc-custom-crf" hidden>
					<label for="tvc-crf-slider" class="tvc-custom-crf__label">
						CRF: <strong id="tvc-crf-value">28</strong>
					</label>
					<input type="range" id="tvc-crf-slider" min="0" max="63" value="28" step="1">
					<span class="tvc-custom-crf__hint">0 = lossless &nbsp;·&nbsp; 28 = balanced &nbsp;·&nbsp; 63 = smallest</span>
				</div>
			</div>

			<!-- Convert button -->
			<div class="tvc-actions">
				<button id="tvc-convert-btn" class="tvc-btn tvc-btn--primary tvc-btn--lg" disabled>
					Convert to WebM
				</button>
			</div>

			<!-- Keep-tab-active notice (hidden until conversion starts) -->
			<div id="tvc-active-notice" class="tvc-active-notice" hidden role="alert">
				<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
				Keep this tab active &mdash; do not navigate away until processing is finished.
			</div>

			<!-- Progress (hidden until encode starts) -->
			<div id="tvc-progress-wrap" class="tvc-progress-wrap" hidden>
				<div class="tvc-progress-label">
					<span id="tvc-progress-stage">Encoding…</span>
					<span id="tvc-progress-pct">0%</span>
				</div>
				<div class="tvc-progress-bar-wrap">
					<div id="tvc-progress-bar" class="tvc-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%"></div>
				</div>
				<p id="tvc-progress-detail" class="tvc-progress-detail"></p>
			</div>

			<!-- Error -->
			<div id="tvc-error" class="tvc-error" hidden role="alert"></div>

		</div><!-- /tvc-view-convert -->

		<!-- VIEW: Result (shown when conversion is complete) -->
		<div id="tvc-view-result" class="tvc-view" hidden>
			<div class="tvc-result__done-icon" aria-hidden="true">✓</div>
			<h2 class="tvc-result__title">Conversion complete</h2>
			<div class="tvc-result__row">
				<span class="tvc-result__label">WebM</span>
				<a id="tvc-download-webm" class="tvc-btn tvc-btn--primary" download>Download WebM</a>
				<span id="tvc-result-size-webm" class="tvc-result__size"></span>
			</div>
			<div id="tvc-result-poster-row" class="tvc-result__row" hidden>
				<span class="tvc-result__label">Poster</span>
				<a id="tvc-download-poster" class="tvc-btn tvc-btn--ghost" download>Download Poster JPG</a>
			</div>
			<div class="tvc-result__savings" id="tvc-result-savings"></div>
			<button id="tvc-convert-another" class="tvc-btn tvc-btn--ghost tvc-btn--sm">Convert another file</button>
		</div><!-- /tvc-view-result -->

	</main>

	<!-- FAQ / info strip -->
	<section class="tvc-info-strip">
		<div class="tvc-info-strip__inner">
			<div class="tvc-info-item">
				<strong>🔒 Privacy first</strong>
				<p>Your video never leaves your computer. Everything runs inside your browser tab.</p>
			</div>
			<div class="tvc-info-item">
				<strong>⚡ Web-optimised output</strong>
				<p>Outputs include seek tables, keyframe tuning, and an optional poster frame for instant perceived load.</p>
			</div>
			<div class="tvc-info-item">
				<strong>🎞 VP9 + Opus</strong>
				<p>Industry-standard WebM encoding with Opus audio. Supported by all modern browsers.</p>
			</div>
		</div>
	</section>

	<footer class="tvc-footer">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?> &mdash; <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Back to site</a></p>
	</footer>

</div><!-- #tm-converter-root -->

<?php wp_footer(); ?>
</body>
</html>
