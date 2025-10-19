// 简单的全站图片缓存刷新器：为同域图片追加版本参数，绕过历史错误缓存
// 修改 VERSION 即可全站统一刷新（不需要逐页改链接）
const VERSION = "2025-08-31-1";

self.addEventListener('install', event => {
  // 立即接管，无需等待
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  // 激活后让新 SW 立即控制所有页面
  event.waitUntil(self.clients.claim());
});

function shouldBust(url) {
  try {
    const u = new URL(url);
    if (u.origin !== self.location.origin) return false; // 仅处理同域
    // 仅处理常见图片后缀
    return /\.(?:png|jpe?g|webp|gif|svg)$/i.test(u.pathname);
  } catch (_) { return false; }
}

self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);

  if (req.method !== 'GET') return;
  if (!shouldBust(url.href)) return;

  // 已带 v 参数则不处理
  if (url.searchParams.has('v')) return;

  url.searchParams.set('v', VERSION);

  // 使用 reload 强制绕过中间缓存（由浏览器/边缘决定是否生效）
  const bustRequest = new Request(url.toString(), {
    method: 'GET',
    headers: req.headers,
    mode: req.mode,
    credentials: req.credentials,
    cache: 'reload',
    redirect: req.redirect,
    referrer: req.referrer,
    referrerPolicy: req.referrerPolicy,
    integrity: req.integrity,
  });

  event.respondWith(fetch(bustRequest).catch(() => fetch(req)));
});


