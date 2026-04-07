/**
 * TM Video Tools — Browser-side WebM converter
 *
 * Uses @ffmpeg/ffmpeg v0.12.x (ESM) self-hosted in the plugin's assets/ffmpeg/ folder.
 * All processing runs on the user's CPU via WebAssembly — no upload.
 *
 * Features:
 *  - FFmpeg loading progress banner
 *  - File-size limits (guests 20 MB, logged-in 200 MB)
 *  - Quality presets + custom CRF slider
 *  - VP9 + Opus WebM with full web-optimisation flags
 *  - Poster-frame extraction (0.5 s keyframe as JPG)
 *  - "Keep tab active" warning during encode
 */
(async function () {
	"use strict";

	/* ------------------------------------------------------------------ */
	/* Config from WordPress (tmVideoTools set via wp_localize_script)     */
	/* ------------------------------------------------------------------ */
	var cfg = window.tmVideoTools || {};
	var MAX_BYTES   = (cfg.maxFileSizeMB || 20) * 1024 * 1024;
	var IS_LOGGED   = !!cfg.isLoggedIn;
	var LOGIN_URL   = cfg.loginUrl || "/wp-login.php";
	// Self-hosted ffmpeg assets (MT build) — same origin, no cross-origin Worker restrictions.
	var FFMPEG_URL    = cfg.ffmpegUrl       || "";
	var FFMPEG_CORE   = cfg.ffmpegCoreUrl   || "";
	var FFMPEG_WASM   = cfg.ffmpegWasmUrl   || "";
	var FFMPEG_WORKER = cfg.ffmpegWorkerUrl || "";

	/* Quality map: preset name → CRF value */
	var QUALITY_MAP = { hq: 18, balanced: 28, compressed: 40 };

	/* ------------------------------------------------------------------ */
	/* DOM refs                                                             */
	/* ------------------------------------------------------------------ */
	var $ = function (id) { return document.getElementById(id); };

	var elDropzone      = $("tvc-dropzone");
	var elFileInput     = $("tvc-file-input");
	var elFileInfo      = $("tvc-file-info");
	var elFileName      = $("tvc-file-name");
	var elFileSize      = $("tvc-file-size");
	var elFileClear     = $("tvc-file-clear");
	var elSizeLimit     = $("tvc-size-limit");
	var elConvertBtn    = $("tvc-convert-btn");
	var elActiveNotice  = $("tvc-active-notice");
	var elProgressWrap  = $("tvc-progress-wrap");
	var elProgressBar   = $("tvc-progress-bar");
	var elProgressPct   = $("tvc-progress-pct");
	var elProgressStage = $("tvc-progress-stage");
	var elProgressDetail= $("tvc-progress-detail");
	var elDownloadWebm  = $("tvc-download-webm");
	var elDownloadPoster= $("tvc-download-poster");
	var elResultSizeWebm= $("tvc-result-size-webm");
	var elResultPosterRow = $("tvc-result-poster-row");
	var elResultSavings = $("tvc-result-savings");
	var elConvertAnother= $("tvc-convert-another");
	var elError         = $("tvc-error");
	var elFfmpegLoading = $("tvc-ffmpeg-loading");
	var elFfmpegBar     = $("tvc-ffmpeg-bar");
	var elFfmpegPct     = $("tvc-ffmpeg-pct");
	var elFfmpegStatus  = $("tvc-ffmpeg-status");
	var elViewConvert   = $("tvc-view-convert");
	var elViewResult    = $("tvc-view-result");
	var elCustomCrfWrap = $("tvc-custom-crf-wrap");
	var elCrfSlider     = $("tvc-crf-slider");
	var elCrfValue      = $("tvc-crf-value");

	/* ------------------------------------------------------------------ */
	/* State                                                                */
	/* ------------------------------------------------------------------ */
	var selectedFile = null;
	var ffmpegInstance = null;
	var ffmpegLoaded = false;
	var isConverting = false;

	/* ------------------------------------------------------------------ */
	/* Utilities                                                            */
	/* ------------------------------------------------------------------ */
	function humanBytes(b) {
		if (b < 1024) return b + " B";
		if (b < 1024 * 1024) return (b / 1024).toFixed(1) + " KB";
		return (b / (1024 * 1024)).toFixed(1) + " MB";
	}

	function getSelectedCrf() {
		var radios = document.querySelectorAll("input[name='tvc-quality']");
		for (var i = 0; i < radios.length; i++) {
			if (radios[i].checked) {
				var v = radios[i].value;
				if (v === "custom") return parseInt(elCrfSlider.value, 10);
				return QUALITY_MAP[v] !== undefined ? QUALITY_MAP[v] : 28;
			}
		}
		return 28;
	}

	function setProgress(pct, stage, detail) {
		pct = Math.max(0, Math.min(100, pct));
		elProgressBar.style.width = pct + "%";
		elProgressBar.setAttribute("aria-valuenow", pct);
		elProgressPct.textContent = pct + "%";
		if (stage)  elProgressStage.textContent = stage;
		if (detail) elProgressDetail.textContent = detail;
	}

	function showError(msg) {
		elError.textContent = msg;
		elError.hidden = false;
	}

	function clearError() {
		elError.hidden = true;
		elError.textContent = "";
	}

	function show(el) { el.hidden = false; }
	function hide(el) { el.hidden = true; }

	/* ------------------------------------------------------------------ */
	/* File size limit UI                                                   */
	/* ------------------------------------------------------------------ */
	elSizeLimit.textContent = IS_LOGGED
		? "Max file size: 200 MB (logged in)"
		: "Max file size: 20 MB — Log in for up to 200 MB";

	/* ------------------------------------------------------------------ */
	/* File selection                                                       */
	/* ------------------------------------------------------------------ */
	function applyFile(file) {
		clearError();
		if (!file || !file.type.startsWith("video/")) {
			showError("Please select a video file (MP4 or similar).");
			return;
		}
		if (file.size > MAX_BYTES) {
			var limitMb = IS_LOGGED ? "200 MB" : "20 MB";
			showError(
				"File is too large (" + humanBytes(file.size) + "). " +
				"Limit is " + limitMb + " for " +
				(IS_LOGGED ? "logged-in users." : "guests. ") +
				(!IS_LOGGED ? '<a href="' + LOGIN_URL + '">Log in</a> for 200 MB.' : "")
			);
			return;
		}
		selectedFile = file;
		elFileName.textContent = file.name;
		elFileSize.textContent = humanBytes(file.size);
		show(elFileInfo);
		hide(elDropzone);
		elConvertBtn.disabled = false;
	}

	elFileInput.addEventListener("change", function () {
		if (elFileInput.files && elFileInput.files[0]) applyFile(elFileInput.files[0]);
	});

	elDropzone.addEventListener("dragover", function (e) {
		e.preventDefault();
		elDropzone.classList.add("is-drag-over");
	});
	elDropzone.addEventListener("dragleave", function () {
		elDropzone.classList.remove("is-drag-over");
	});
	elDropzone.addEventListener("drop", function (e) {
		e.preventDefault();
		elDropzone.classList.remove("is-drag-over");
		var files = e.dataTransfer && e.dataTransfer.files;
		if (files && files[0]) applyFile(files[0]);
	});

	elFileClear.addEventListener("click", resetUI);

	/* ------------------------------------------------------------------ */
	/* Quality preset / custom CRF                                         */
	/* ------------------------------------------------------------------ */
	document.querySelectorAll("input[name='tvc-quality']").forEach(function (radio) {
		radio.addEventListener("change", function () {
			// Custom CRF wrap is hidden (no custom option) — kept for future use.
		});
	});
	elCrfSlider.addEventListener("input", function () {
		elCrfValue.textContent = elCrfSlider.value;
	});

	/* ------------------------------------------------------------------ */
	/* Load ffmpeg.wasm (self-hosted, same-origin ESM)                     */
	/* ------------------------------------------------------------------ */
	async function initFfmpeg() {
		if (ffmpegLoaded && ffmpegInstance) return ffmpegInstance;

		// Multi-threaded wasm requires SharedArrayBuffer, which needs cross-origin isolation.
		if (!self.crossOriginIsolated) {
			throw new Error(
				"This page is not cross-origin isolated. " +
				"Please do a hard reload (Ctrl+Shift+R / Cmd+Shift+R) and try again."
			);
		}

		show(elFfmpegLoading);
		setFfmpegProgress(0);
		setFfmpegStatus("Loading FFmpeg module…");
		simulateFfmpegDownloadProgress();

		try {
			// Self-hosted ESM: same-origin — Worker loads without any blob URL wrapping.
			var mod = await import(FFMPEG_URL);
			var FFmpeg = mod.FFmpeg;

			var ff = new FFmpeg();
			setFfmpegStatus("Initialising WebAssembly engine…");

			await ff.load({
				coreURL:   FFMPEG_CORE,
				wasmURL:   FFMPEG_WASM,
				workerURL: FFMPEG_WORKER,
			});

			ffmpegInstance = ff;
			ffmpegLoaded   = true;
			setFfmpegProgress(100);
			setTimeout(function () { hide(elFfmpegLoading); }, 600);
			return ff;
		} catch (e) {
			hide(elFfmpegLoading);
			throw e || new Error("Failed to initialise FFmpeg.");
		}
	}

	function simulateFfmpegDownloadProgress() {
		// The wasm fetch is a Fetch API request that the library makes internally.
		// We cannot intercept its progress, so we show a smooth animation up to
		// 90% and then jump to 100% when load() resolves.
		var pct = 0;
		var iv = setInterval(function () {
			pct = Math.min(pct + (Math.random() * 3 + 0.5), 90);
			setFfmpegProgress(pct);
			if (pct >= 90) clearInterval(iv);
		}, 250);
	}

	function setFfmpegProgress(pct) {
		pct = Math.round(pct);
		elFfmpegBar.style.width = pct + "%";
		elFfmpegBar.setAttribute("aria-valuenow", pct);
		elFfmpegPct.textContent = pct + "%";
	}

	function setFfmpegStatus(text) {
		if (elFfmpegStatus) elFfmpegStatus.textContent = text;
	}

	/* ------------------------------------------------------------------ */
	/* Convert                                                              */
	/* ------------------------------------------------------------------ */
	elConvertBtn.addEventListener("click", doConvert);

	function doConvert() {
		if (isConverting)  return;
		if (!selectedFile) return;
		clearError();
		doConvertWithFile(selectedFile, getSelectedCrf());
	}

	function doConvertWithFile(file, crf) {
		isConverting = true;

		// UI state
		elConvertBtn.disabled = true;
		show(elActiveNotice);
		show(elProgressWrap);
		hide(elError);
		setProgress(0, "Loading FFmpeg…", "");

		// Unload warning
		window.addEventListener("beforeunload", onBeforeUnload);

		initFfmpeg().then(function (ff) {
			setProgress(2, "Reading file…", "");

			var reader = new FileReader();
			reader.onload = function (e) {
				runEncode(ff, file, new Uint8Array(e.target.result), crf);
			};
			reader.onerror = function () {
				finishConvert();
				showError("Failed to read file.");
			};
			reader.readAsArrayBuffer(file);
		}).catch(function (err) {
			finishConvert();
			showError("Could not load FFmpeg: " + (err && err.message ? err.message : String(err)));
		});
	}

	function runEncode(ff, file, inputBytes, crf) {
		var baseName = file.name.replace(/\.[^.]+$/, "");
		var inName   = "input.mp4";
		var outWebm  = "output.webm";
		var outPoster = "poster.jpg";

		// Capture ffmpeg log messages so we can show them in error output.
		var encodeLog = [];
		var logHandler = function (e) {
			if (e && e.message) encodeLog.push(e.message);
		};
		ff.on("log", logHandler);

		// Progress listener — fired by ffmpeg.wasm during encode
		var progressHandler = function (e) {
			if (e && typeof e.progress === "number" && e.progress >= 0) {
				var pct = Math.min(Math.round(e.progress * 100), 99);
				setProgress(pct, "Encoding VP9…", "");
			}
		};
		ff.on("progress", progressHandler);

		setProgress(5, "Writing input to memory…", "");

		Promise.resolve()
			.then(function () {
				return ff.writeFile(inName, inputBytes);
			})
			.then(function () {
				setProgress(8, "Encoding VP9 + Opus…", "This may take a few minutes for longer videos.");

				/*
				 * VP9 flags for multi-threaded wasm (@ffmpeg/core-mt, SharedArrayBuffer):
				 *   -vf scale                 cap to 1280x720 max (even-dim enforcement)
				 *   -b:v 0 -crf <n>          constant-quality mode
				 *   -deadline good            balanced quality/speed (viable with MT)
				 *   -cpu-used 4              balanced MT speed
				 *   -row-mt 1               row-level parallelism
				 *   -auto-alt-ref 1          alt-ref frames for compression efficiency
				 *   -lag-in-frames 16        lookahead for better decisions
				 *   -tile-columns 2          VP9 tiling for MT decode/encode
				 *   -pix_fmt yuv420p         universal playback compatibility
				 *   -g 120 -keyint_min 120   keyframe every 4 s at 30fps
				 *   -ac 2 -ar 44100          normalise audio to stereo 44.1 kHz
				 *   -c:a libopus -b:a 96k    Opus audio at 96 kbps
				 */
				return ff.exec([
					"-i",             inName,
					"-vf",            "scale=1280:720:force_original_aspect_ratio=decrease,scale=trunc(iw/2)*2:trunc(ih/2)*2",
					"-c:v",           "libvpx-vp9",
					"-b:v",           "0",
					"-crf",           String(crf),
					"-deadline",      "good",
					"-cpu-used",      "4",
					"-row-mt",        "1",
					"-auto-alt-ref",  "1",
					"-lag-in-frames", "16",
					"-tile-columns",  "2",
					"-pix_fmt",       "yuv420p",
					"-g",             "120",
					"-keyint_min",    "120",
					"-ac",            "2",
					"-ar",            "44100",
					"-c:a",           "libopus",
					"-b:a",           "96k",
					"-y",             outWebm,
				]);
			})
			.then(function (ret) {
				// exec resolves with ffmpeg exit code — non-zero means encoding failed.
				if (ret !== 0) {
					var logSnippet = encodeLog.slice(-6).join("\n");
					throw new Error("FFmpeg exited with code " + ret + (logSnippet ? "\n" + logSnippet : ""));
				}
				setProgress(95, "Reading output…", "");
				return ff.readFile(outWebm);
			})
			.then(function (webmData) {
				ff.off("progress", progressHandler);
				ff.off("log", logHandler);
				setProgress(97, "Extracting poster frame…", "");

				var webmBlob = new Blob([webmData.buffer], { type: "video/webm" });
				var webmUrl  = URL.createObjectURL(webmBlob);
				elDownloadWebm.href     = webmUrl;
				elDownloadWebm.download = baseName + ".webm";
				elResultSizeWebm.textContent = humanBytes(webmBlob.size);

				var savings = Math.round((1 - webmBlob.size / file.size) * 100);
				elResultSavings.textContent = savings > 0
					? "⬇ " + savings + "% smaller than original (" + humanBytes(file.size) + " → " + humanBytes(webmBlob.size) + ")"
					: "File size: " + humanBytes(webmBlob.size);

				// Always extract poster at 0.5 s
				return ff.exec([
					"-ss",   "0.5",
					"-i",    inName,
					"-vframes", "1",
					"-q:v",  "2",
					"-y",    outPoster,
				]).then(function () {
					return ff.readFile(outPoster).catch(function () { return null; });
				}).catch(function () { return null; });
			})
			.then(function (posterData) {
				if (posterData) {
					var posterBlob = new Blob([posterData.buffer], { type: "image/jpeg" });
					var posterUrl  = URL.createObjectURL(posterBlob);
					elDownloadPoster.href     = posterUrl;
					elDownloadPoster.download = baseName + "-poster.jpg";
					show(elResultPosterRow);
				} else {
					hide(elResultPosterRow);
				}

				setProgress(100, "Done!", "");
				// Switch to result view
				hide(elViewConvert);
				show(elViewResult);
				finishConvert();
			})
			.catch(function (err) {
				ff.off("progress", progressHandler);
				ff.off("log", logHandler);
				finishConvert();
				var msg = err && err.message ? err.message : String(err);
				showError("Conversion failed: " + msg + ". Try a smaller file or a different quality preset.");
			})
			.finally(function () {
				// deleteFile is async — swallow rejections (file may not exist on error path).
				var noop = function () {};
				ff.deleteFile(inName).catch(noop);
				ff.deleteFile(outWebm).catch(noop);
				ff.deleteFile(outPoster).catch(noop);
			});
	}

	function finishConvert() {
		isConverting = false;
		hide(elActiveNotice);
		elConvertBtn.disabled = false;
		window.removeEventListener("beforeunload", onBeforeUnload);
	}

	function onBeforeUnload(e) {
		e.preventDefault();
		e.returnValue = "Video conversion is in progress. Are you sure you want to leave?";
	}

	/* ------------------------------------------------------------------ */
	/* Reset / convert another                                             */
	/* ------------------------------------------------------------------ */
	function resetUI() {
		selectedFile = null;
		elFileInput.value = "";
		hide(elFileInfo);
		show(elDropzone);
		elConvertBtn.disabled = true;
		hide(elProgressWrap);
		// Switch back to convert view
		hide(elViewResult);
		show(elViewConvert);
		clearError();
		setProgress(0, "Encoding…", "");
	}

	elConvertAnother.addEventListener("click", resetUI);

	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */

})();
