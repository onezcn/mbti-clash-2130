# 第三方依赖许可说明 (Third-party notices)

本项目代码采用 MIT 许可，但依赖了以下第三方组件，分发时请遵守各自的许可证义务：

## 服务端依赖（package.json）

| 组件 | 许可证 | 来源 |
| --- | --- | --- |
| `@php-wasm/node` | GPL-2.0-or-later | https://github.com/WordPress/wordpress-playground |
| `@php-wasm/universal` | GPL-2.0-or-later | https://github.com/WordPress/wordpress-playground |
| `@seald-io/nedb` | MIT | https://github.com/seald/nedb |
| `ws` | MIT | https://github.com/websockets/ws |
| PHP（WASM 内嵌运行时） | PHP License 3.01 | https://www.php.net/license/ |

## 前端静态库（位于 `js/`、`static/`）

| 组件 | 许可证 |
| --- | --- |
| `js/tailwindcss.js` | MIT |
| `js/jquery-3.6.0.min.js` | MIT |
| `js/html2canvas.min.js` | MIT |
| `js/onez.market.js` | MIT（本项目） |
| `static/font-awesome/6.7.2` | Font Awesome Free License（Icons: CC BY 4.0；Fonts: SIL OFL 1.1；Code: MIT） |

## GPL 组件注意事项

`@php-wasm/*` 为 GPL-2.0-or-later。分发（包含向客户交付）时需：
1. 随附 GPL-2.0 许可证文本；
2. 提供相应组件的源代码获取方式；
3. 评估你的专有代码与 GPL 组件的结合方式是否触发整体开源义务（建议咨询法律意见）。

详见各组件自带的 LICENSE / package.json 中的 license 字段。
