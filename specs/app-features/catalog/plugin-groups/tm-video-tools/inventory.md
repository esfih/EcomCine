# Inventory — tm-video-tools

## Group Summary

Browser-side video conversion tool. Vendors upload a source video (MP4/MOV/AVI/etc.) and the browser converts it to WebM/VP9+Opus using a self-hosted `ffmpeg.wasm` instance — no server-side processing required. Output is downloaded directly by the vendor.

Located at: `ecomcine/modules/tm-video-tools/`

## Feature Inventory

1. Browser-side WebM/VP9 converter page (`/converter`)
   - Phase 1 implementation: partially complete — FFmpeg loads, UI is functional, encoding crashes at ~8% due to wasm memory limits
   - Deferred to Phase 2

## Migration Risk Notes

- No WP adapter dependency — fully browser-side feature
- wasm heap is the primary constraint (256MB single-threaded limit in @ffmpeg/ffmpeg v0.12.x)

## Parity Oracle

- N/A — no legacy implementation to compare against
