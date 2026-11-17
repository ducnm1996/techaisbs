import { fileURLToPath } from 'node:url';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StdioClientTransport } from '@modelcontextprotocol/sdk/client/stdio.js';

const client = new Client({ name: 'techaisbs-local-demo', version: '1.0.0' });
const transport = new StdioClientTransport({
  command: process.execPath,
  args: [fileURLToPath(new URL('./mcp-server.js', import.meta.url))],
});

try {
  await client.connect(transport);
  const { tools } = await client.listTools();
  console.log('MCP tools:', tools.map(tool => tool.name).join(', '));
  const result = await client.callTool({ name: 'read_homepage', arguments: {} });
  if (result.isError) throw new Error(JSON.stringify(result.content));
  for (const item of result.content) {
    if (item.type === 'text') console.log(item.text);
  }
} finally {
  await client.close();
}
