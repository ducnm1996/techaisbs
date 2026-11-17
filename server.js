import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';

const homepage = {
  type: 'text/html; charset=utf-8',
  body: await readFile(new URL('./index.html', import.meta.url)),
};
const stylesheet = {
  type: 'text/css; charset=utf-8',
  body: await readFile(new URL('./styles.css', import.meta.url)),
};
const routes = new Map([
  ['/', homepage],
  ['/index.html', homepage],
  ['/styles.css', stylesheet],
]);

const host = process.env.HOST ?? '127.0.0.1';
const port = Number(process.env.PORT ?? '3000');

const server = createServer((request, response) => {
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    response.writeHead(405, { Allow: 'GET, HEAD' }).end();
    return;
  }

  const path = request.url.split('?', 1)[0];
  const page = routes.get(path);
  if (!page) {
    response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end(request.method === 'HEAD' ? undefined : 'Không tìm thấy trang.');
    return;
  }

  response.writeHead(200, {
    'Content-Type': page.type,
    'Content-Length': page.body.length,
  });
  response.end(request.method === 'HEAD' ? undefined : page.body);
});

server.listen(port, host, () => {
  const { address, port: boundPort, family } = server.address();
  const urlHost = family === 'IPv6' ? `[${address}]` : address;
  console.log(`TechAI SBS: http://${urlHost}:${boundPort}`);
});
