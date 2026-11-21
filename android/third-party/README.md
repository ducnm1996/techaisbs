# Native tunnel dependency

The Android app vendors the unmodified upstream release AAR:

- Project: https://github.com/heiher/hev-socks5-tunnel
- Tag: 2.20.0
- Tag commit: 75dbc1e9ace6c70d3d1b94df7c283efe1de7effc
- Release asset: https://github.com/heiher/hev-socks5-tunnel/releases/download/2.20.0/hev-socks5-tunnel.aar
- File: app/libs/hev-socks5-tunnel-2.20.0.aar
- SHA-256 (verified against GitHub release asset digest): ae8a2e96b80b4eccccd037805a4bb4a278090c89dbcae8bc6fcb7634e9a255b9
- ABIs: arm64-v8a, armeabi-v7a, x86_64, x86. Minimum Android API 29.
- JNI contract: hev.htproxy.TProxyService; no repackaging or native overrides.

The AAR is a prebuilt release artifact, not rebuilt from C source during this app build. Source and native build instructions are at the pinned tag. Native source and its submodules can be reproduced with the upstream ndk-build instructions. Check the artifact digest before replacing it; never silently update to latest. This is a local tun2socks engine, not a hosted VPN gateway.

Bundled upstream components and pins:

- hev-socks5-core: b43fa857fb39c854da01d13bc55eded66b4f4a3b
- hev-task-system: ded55c412b3951bc7decb5cb3b9135701e762998
- lwip: 8115cd6150ab32500db3d4d8bd8ad7ec6648534c
- yaml: 0c82efd10dc67b9e7f159d2c7f5d1dc3c1b5bc11

License notices are in this directory and packaged as app assets. Hev components/YAML use MIT terms; lwIP uses BSD terms. The library receives IP packets only for forwarding; native logging is directed to /dev/null, no PCAP output is enabled. Application payload is never persisted or uploaded. Destination metadata is extracted by the app's separate header-only parser before forwarding and is encrypted before queueing.
