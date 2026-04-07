(function () {
  "use strict";

  var $ = function (id) { return document.getElementById(id); };

  var elDropzone = $("dropzone");
  var elFileInput = $("file-input");
  var elFileInfo = $("file-info");
  var elFileName = $("file-name");
  var elFileSize = $("file-size");
  var elFileClear = $("file-clear");
  var elConvertBtn = $("convert-btn");
  var elActiveNotice = $("active-notice");
  var elProgressWrap = $("progress-wrap");
  var elProgressBar = $("progress-bar");
  var elProgressStage = $("progress-stage");
  var elProgressPct = $("progress-pct");
  var elProgressDetail = $("progress-detail");
  var elErrorBox = $("error-box");
  var elRuntimeBanner = $("runtime-banner");
  var elViewConvert = $("view-convert");
  var elViewResult = $("view-result");
  var elDownloadWebm = $("download-webm");
  var elDownloadPoster = $("download-poster");
  var elResultSizeWebm = $("result-size-webm");
  var elResultPosterRow = $("result-poster-row");
  var elResultSizePoster = $("result-size-poster");
  var elResultSavings = $("result-savings");
  var elConvertAnother = $("convert-another");

  var QUALITY_MAP = {
    hq: "HQ Archive",
    balanced: "Balanced",
    compressed: "Compressed"
  };

  var selectedFile = null;
  var currentJobId = "";
  var pollTimer = null;
  var isConverting = false;
  var debugMode = new URLSearchParams(window.location.search).has("debug");

  function humanBytes(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / (1024 * 1024)).toFixed(1) + " MB";
  }

  function show(el) {
    if (el) el.hidden = false;
  }

  function hide(el) {
    if (el) el.hidden = true;
  }

  function setBanner(kind, text) {
    if (!debugMode) {
      return;
    }
    elRuntimeBanner.className = "runtime-banner" + (kind ? " is-" + kind : "");
    elRuntimeBanner.textContent = text;
    show(elRuntimeBanner);
  }

  function clearBanner() {
    elRuntimeBanner.className = "runtime-banner";
    elRuntimeBanner.textContent = "";
    hide(elRuntimeBanner);
  }

  function showError(message) {
    elErrorBox.textContent = message;
    show(elErrorBox);
  }

  function clearError() {
    elErrorBox.textContent = "";
    hide(elErrorBox);
  }

  function setProgress(percent, stage, detail) {
    var bounded = Math.max(0, Math.min(100, percent || 0));
    elProgressBar.style.width = bounded + "%";
    elProgressBar.setAttribute("aria-valuenow", String(bounded));
    elProgressPct.textContent = bounded + "%";
    if (stage !== undefined) elProgressStage.textContent = stage;
    if (detail !== undefined) elProgressDetail.textContent = detail;
  }

  function getSelectedPreset() {
    var radios = document.querySelectorAll('input[name="quality"]');
    for (var i = 0; i < radios.length; i += 1) {
      if (radios[i].checked) {
        return radios[i].value;
      }
    }
    return "balanced";
  }

  function resetUI() {
    selectedFile = null;
    currentJobId = "";
    isConverting = false;
    if (pollTimer) {
      window.clearTimeout(pollTimer);
      pollTimer = null;
    }

    clearError();
    show(elDropzone);
    hide(elFileInfo);
    hide(elProgressWrap);
    hide(elActiveNotice);
    hide(elViewResult);
    show(elViewConvert);
    elConvertBtn.disabled = true;
    elFileInput.value = "";
    elResultSavings.textContent = "";
    hide(elResultPosterRow);
    elDownloadWebm.removeAttribute("href");
    elDownloadPoster.removeAttribute("href");
    setProgress(0, "Preparing…", "");
  }

  function applyFile(file) {
    clearError();

    if (!file || !file.type || file.type.indexOf("video/") !== 0) {
      showError("Please choose a video file.");
      return;
    }

    selectedFile = file;
    elFileName.textContent = file.name;
    elFileSize.textContent = humanBytes(file.size);
    show(elFileInfo);
    hide(elDropzone);
    elConvertBtn.disabled = false;
  }

  function completeWithResult(job) {
    isConverting = false;
    hide(elActiveNotice);
    hide(elProgressWrap);
    hide(elViewConvert);
    show(elViewResult);

    elDownloadWebm.href = job.downloads.webm.url;
    elDownloadWebm.download = job.downloads.webm.fileName;
    elResultSizeWebm.textContent = humanBytes(job.downloads.webm.sizeBytes || 0);

    if (job.downloads.poster) {
      show(elResultPosterRow);
      elDownloadPoster.href = job.downloads.poster.url;
      elDownloadPoster.download = job.downloads.poster.fileName;
      elResultSizePoster.textContent = humanBytes(job.downloads.poster.sizeBytes || 0);
    } else {
      hide(elResultPosterRow);
    }

    var savings = "Source: " + humanBytes(job.sourceSizeBytes || 0) + " → WebM: " + humanBytes(job.downloads.webm.sizeBytes || 0);
    if (job.downloads.webm.sizeBytes && job.sourceSizeBytes) {
      var delta = ((job.sourceSizeBytes - job.downloads.webm.sizeBytes) / job.sourceSizeBytes) * 100;
      if (Number.isFinite(delta)) {
        savings += " (" + delta.toFixed(1) + "% smaller)";
      }
    }
    elResultSavings.textContent = savings;
  }

  function pollJob(jobId) {
    fetch("/api/jobs/" + encodeURIComponent(jobId), { cache: "no-store" })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Job polling failed with status " + response.status + ".");
        }
        return response.json();
      })
      .then(function (job) {
        if (job.status === "failed") {
          isConverting = false;
          hide(elActiveNotice);
          show(elProgressWrap);
          setProgress(job.progressPercent || 0, job.stage || "Failed", job.detail || "");
          showError(job.error || "Conversion failed.");
          return;
        }

        if (job.status === "complete") {
          completeWithResult(job);
          return;
        }

        setProgress(job.progressPercent || 0, job.stage || "Processing…", job.detail || "");
        pollTimer = window.setTimeout(function () {
          pollJob(jobId);
        }, 900);
      })
      .catch(function (error) {
        isConverting = false;
        hide(elActiveNotice);
        showError(error.message || "Could not read conversion status.");
      });
  }

  function startUpload() {
    if (!selectedFile || isConverting) {
      return;
    }

    isConverting = true;
    clearError();
    show(elActiveNotice);
    show(elProgressWrap);
    setProgress(0, "Uploading source…", "Sending video to converter service.");
    elConvertBtn.disabled = true;

    var preset = getSelectedPreset();
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "/api/jobs?preset=" + encodeURIComponent(preset), true);
    xhr.setRequestHeader("X-File-Name", encodeURIComponent(selectedFile.name));
    xhr.setRequestHeader("Content-Type", selectedFile.type || "application/octet-stream");

    xhr.upload.onprogress = function (event) {
      if (!event.lengthComputable) {
        return;
      }
      var percent = Math.round((event.loaded / event.total) * 25);
      setProgress(percent, "Uploading source…", "Preset: " + QUALITY_MAP[preset]);
    };

    xhr.onload = function () {
      if (xhr.status < 200 || xhr.status >= 300) {
        isConverting = false;
        showError("Upload failed with status " + xhr.status + ".");
        return;
      }

      try {
        var payload = JSON.parse(xhr.responseText || "{}");
        currentJobId = payload.jobId || "";
        if (!currentJobId) {
          throw new Error("No job ID returned by server.");
        }
        setProgress(25, "Queued…", "Upload complete. Starting native ffmpeg conversion.");
        pollJob(currentJobId);
      } catch (error) {
        isConverting = false;
        showError(error.message || "Invalid server response.");
      }
    };

    xhr.onerror = function () {
      isConverting = false;
      showError("Network error while uploading the video.");
    };

    xhr.send(selectedFile);
  }

  function loadConfig() {
    if (window.location.protocol === "file:") {
      showError("This converter needs its local server. Start start.sh or start.bat, then open the local URL it prints.");
      setBanner("error", "This converter needs its local server. Start start.sh or start.bat, then open the local URL it prints.");
      return;
    }

    fetch("/api/config", { cache: "no-store" })
      .then(function (response) {
        if (!response.ok) {
          throw new Error("Could not reach the converter runtime.");
        }
        return response.json();
      })
      .then(function (config) {
        if (!config.ffmpegAvailable) {
          showError("ffmpeg is not available to this standalone converter. Put ffmpeg in bin/ or on PATH.");
          setBanner("error", "ffmpeg is not available to this standalone converter. Put ffmpeg in bin/ or on PATH.");
          return;
        }

        clearBanner();
        clearError();
        setBanner("success", "Ready. ffmpeg runtime detected at " + config.ffmpegPath + ".");
      })
      .catch(function (error) {
        showError(error.message || "Could not connect to the converter runtime.");
        setBanner("error", error.message || "Could not connect to the converter runtime.");
      });
  }

  elDropzone.addEventListener("click", function () {
    elFileInput.click();
  });

  elDropzone.addEventListener("keydown", function (event) {
    if (event.key === "Enter" || event.key === " ") {
      event.preventDefault();
      elFileInput.click();
    }
  });

  elDropzone.addEventListener("dragover", function (event) {
    event.preventDefault();
    elDropzone.classList.add("drag-over");
  });

  elDropzone.addEventListener("dragleave", function () {
    elDropzone.classList.remove("drag-over");
  });

  elDropzone.addEventListener("drop", function (event) {
    event.preventDefault();
    elDropzone.classList.remove("drag-over");
    if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
      applyFile(event.dataTransfer.files[0]);
    }
  });

  elFileInput.addEventListener("change", function () {
    if (elFileInput.files && elFileInput.files[0]) {
      applyFile(elFileInput.files[0]);
    }
  });

  elFileClear.addEventListener("click", resetUI);
  elConvertBtn.addEventListener("click", startUpload);
  elConvertAnother.addEventListener("click", resetUI);

  resetUI();
  loadConfig();
})();