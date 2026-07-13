! function() {
	var e = window.MIRAI_UPDATE_CONFIG && MIRAI_UPDATE_CONFIG.checkUrl,
		i = window.MIRAI_UPDATE_CONFIG && MIRAI_UPDATE_CONFIG.doUrl,
		t = window.MIRAI_UPDATE_CONFIG && MIRAI_UPDATE_CONFIG.token;

	function a(a) {
		var r = a.querySelector(".js-mirai-check-update");
		r && !r.getAttribute("data-bound") && (r.setAttribute("data-bound", "1"), r.addEventListener("click", (function(o) {
			if (o.preventDefault(), "1" !== r.getAttribute("data-loading")) {
				if (!confirm("此操作仅检查 MiraiCore 插件的更新，不包含主题。\n\n主题更新请在「许可激活」标签页中点击检查更新。")) return;
				r.setAttribute("data-loading", "1"), r.disabled = !0;
				var d = r.innerHTML;
				r.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> 检查中...', fetch(e, {
					credentials: "same-origin"
				}).then((function(e) {
					return e.json()
				})).then((function(e) {
					if (r.removeAttribute("data-loading"), r.disabled = !1, r.innerHTML = d, 0 === e.code && e.data) {
						var o = e.data;
						o.has_update ? function(e, a) {
							var r = e.querySelector(".mirai-update-notice");
							r && r.remove();
							var o = document.createElement("div");
							o.className = "mirai-update-notice", o.style.cssText = "margin:10px 0;padding:12px 16px;background:#fff8e1;border:1px solid #ffe082;border-radius:6px;font-size:13px;color:#666;";
							var d = a.body ? a.body.substring(0, 200) : "暂无更新说明",
								l = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><strong style="color:#e65100">发现新版本 v' + a.latest + '</strong><span style="color:#999">当前 v' + a.current + '</span></div><div style="margin-bottom:10px;white-space:pre-line;max-height:120px;overflow:auto;line-height:1.6">' + d + '</div><div style="display:flex;gap:8px;flex-wrap:wrap"><a href="javascript:;" class="mirai-badge is-red js-mirai-do-update" data-url="' + a.download_url + '" data-version="' + a.latest + '"><i class="ri-download-line"></i> 立即更新</a>';
							a.html_url && (l += '<a href="' + a.html_url + '" target="_blank" class="mirai-badge is-blue"><i class="ri-external-link-line"></i> 查看详情</a>'), l += "</div>", o.innerHTML = l;
							var s = e.querySelector(".mirai-config-header");
							s && s.nextSibling ? e.insertBefore(o, s.nextSibling) : e.appendChild(o);
							var c = o.querySelector(".js-mirai-do-update");
							c && c.addEventListener("click", (function(a) {
								if (a.preventDefault(), !c.disabled) {
									c.disabled = !0, c.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> 更新中...';
									var r = new FormData;
									r.append("download_url", c.getAttribute("data-url")), r.append("new_version", c.getAttribute("data-version")), r.append("_token", t), fetch(i, {
										method: "POST",
										body: r,
										credentials: "same-origin"
									}).then((function(e) {
										return e.json()
									})).then((function(i) {
										0 === i.code ? (n(e, i.message || "更新成功", !0), o.remove(), setTimeout((function() {
											window.location.reload()
										}), 1500)) : (c.disabled = !1, c.innerHTML = '<i class="ri-download-line"></i> 立即更新', n(e, i.message || "更新失败", !1))
									})).catch((function() {
										c.disabled = !1, c.innerHTML = '<i class="ri-download-line"></i> 立即更新', n(e, "更新请求失败", !1)
									}))
								}
							}))
						}(a, o) : n(a, e.message || "已是最新版本", !0)
					} else n(a, e.message || "检查更新失败", !1)
				})).catch((function() {
					r.removeAttribute("data-loading"), r.disabled = !1, r.innerHTML = d, n(a, "无法连接服务器", !1)
				}))
			}
		})))
	}

	function n(e, i, t) {
		var a = e.querySelector(".mirai-config-tip");
		a && (a.textContent = i || "", a.style.color = t ? "#16a34a" : "#d93026", a.style.marginTop = "8px", a.style.whiteSpace = "pre-line", a.style.display = i ? "block" : "none")
	}

	function r() {
		var e = document.querySelector(".mirai-config-wrapper");
		e && a(e)
	}
	e && i && ("loading" === document.readyState ? document.addEventListener("DOMContentLoaded", r) : r(), setTimeout(r, 1e3))
}();
