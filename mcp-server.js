import { readFile } from 'node:fs/promises';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { load } from 'cheerio';

const homepage = new URL('./index.html', import.meta.url);
const server = new McpServer({ name: 'techaisbs', version: '1.0.0' });
const normalize = text => text.replace(/\s+/g, ' ').trim();

server.registerTool('read_homepage', {
  title: 'Đọc trang chủ TechAI SBS',
  description: 'Trả về tiêu đề, mô tả và nội dung chính tiếng Việt của trang chủ TechAI SBS.',
  inputSchema: {},
  annotations: { readOnlyHint: true, openWorldHint: false },
}, async () => {
  const $ = load(await readFile(homepage, 'utf8'));
  const title = normalize($('title').first().text());
  const description = $('meta[name="description"]').attr('content') ?? '';
  const main = $('main');
  main.find('[aria-hidden="true"], script, style').remove();
  const sections = main.find('h1, h2, h3, p')
    .map((_, element) => normalize($(element).text())).get().filter(Boolean);

  return {
    content: [{
      type: 'text',
      text: [title, description, ...sections].filter(Boolean).join('\n\n'),
    }],
  };
});

await server.connect(new StdioServerTransport());
