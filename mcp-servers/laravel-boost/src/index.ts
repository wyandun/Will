import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
  type Tool,
} from '@modelcontextprotocol/sdk/types.js';

import { handleProjectMap } from './tools/project-map.js';
import { handleDescribeModel } from './tools/describe-model.js';
import { handleListRoutes } from './tools/list-routes.js';
import { handleDbSchema } from './tools/db-schema.js';
import { handleRunPint, handleRunPhpstan, handleRunTests } from './tools/run-quality.js';
import { handleArtisanCommand } from './tools/artisan.js';
import { handleGetSecurityContext } from './tools/security-context.js';
import { handleEnvironmentHealth } from './tools/environment-health.js';

const TOOLS: Tool[] = [
  {
    name: 'project_map',
    description:
      'Returns a structured overview of the entire SM Portal backend in one call. Covers models, services, controllers, requests, policies, resources, and dependency graph. Use this first before reading individual files.',
    inputSchema: {
      type: 'object',
      properties: {
        section: {
          type: 'string',
          enum: ['models', 'services', 'controllers', 'requests', 'policies', 'resources', 'dependencies', 'all'],
          description: 'Which section to return. Omit for "all".',
        },
      },
    },
  },
  {
    name: 'describe_model',
    description:
      'Full detail for a single Eloquent model — fillable, casts, hidden, traits, relationships, scopes, and (when Docker is running) live DB columns from `php artisan model:show`.',
    inputSchema: {
      type: 'object',
      required: ['model'],
      properties: {
        model: {
          type: 'string',
          description: 'Model class name, e.g. "User", "Franchise", "Company".',
        },
      },
    },
  },
  {
    name: 'list_routes',
    description:
      'Lists all registered API routes with method, URI, middleware, controller, and action. Requires Docker for live data; falls back to parsing routes/api.php.',
    inputSchema: {
      type: 'object',
      properties: {
        filter: {
          type: 'string',
          description: 'Optional string to filter routes by URI or controller name.',
        },
      },
    },
  },
  {
    name: 'db_schema',
    description:
      'Lists all database tables or returns column info for a specific table. Requires Docker (`php artisan db:show` / `db:table`). Falls back to parsing migration files.',
    inputSchema: {
      type: 'object',
      properties: {
        table: {
          type: 'string',
          description: 'Table name (e.g. "users"). Omit to list all tables.',
        },
      },
    },
  },
  {
    name: 'run_pint',
    description:
      'Runs Laravel Pint code style checker inside the Docker container. By default runs in dry-run mode (no changes). Set fix=true to auto-fix.',
    inputSchema: {
      type: 'object',
      properties: {
        fix: {
          type: 'boolean',
          description: 'If true, auto-fix style issues. Default: false (dry-run).',
        },
        path: {
          type: 'string',
          description: 'Optional file or directory to check (relative to backend root).',
        },
      },
    },
  },
  {
    name: 'run_phpstan',
    description:
      'Runs PHPStan static analysis (level 5) inside the Docker container and returns results as JSON.',
    inputSchema: {
      type: 'object',
      properties: {
        path: {
          type: 'string',
          description: 'File or directory to analyse. Defaults to "app/".',
        },
      },
    },
  },
  {
    name: 'run_tests',
    description:
      'Runs the PHPUnit test suite (or a specific file/filter) inside the Docker container using `php artisan test`.',
    inputSchema: {
      type: 'object',
      properties: {
        file: {
          type: 'string',
          description: 'Path to a specific test file (e.g. "tests/Feature/FranchiseTest.php").',
        },
        filter: {
          type: 'string',
          description: 'Method name filter (passed to --filter).',
        },
        suite: {
          type: 'string',
          enum: ['Feature', 'Unit'],
          description: 'Run only Feature or Unit suite.',
        },
      },
    },
  },
  {
    name: 'artisan_command',
    description:
      'Runs an allowlisted Artisan command inside the Docker container. Blocked commands (migrate, db:wipe, tinker, etc.) are rejected.',
    inputSchema: {
      type: 'object',
      required: ['command'],
      properties: {
        command: {
          type: 'string',
          description:
            'Artisan command to run (e.g. "about", "queue:failed", "make:model Post"). Destructive commands are blocked.',
        },
      },
    },
  },
  {
    name: 'get_security_context',
    description:
      'Returns the 15 finalized security decisions from CLAUDE.md. Use this before suggesting any security changes to avoid re-flagging already-reviewed decisions.',
    inputSchema: {
      type: 'object',
      properties: {
        topic: {
          type: 'string',
          description:
            'Optional filter keyword (e.g. "token", "rate-limiting", "mass-assignment", "email"). Returns all if omitted.',
        },
      },
    },
  },
  {
    name: 'environment_health',
    description:
      'Checks the health of the local Docker environment — container status, PostgreSQL, Redis, queue, and API ping. Run this when something seems broken.',
    inputSchema: {
      type: 'object',
      properties: {
        check: {
          type: 'string',
          enum: ['all', 'docker', 'database', 'redis', 'queue', 'api'],
          description: 'Which subsystem to check. Defaults to "all".',
        },
      },
    },
  },
];

const server = new Server(
  { name: 'laravel-boost', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({ tools: TOOLS }));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;
  const a = (args ?? {}) as Record<string, unknown>;

  try {
    switch (name) {
      case 'project_map':
        return { content: [{ type: 'text', text: await handleProjectMap(a.section as string | undefined) }] };

      case 'describe_model':
        return { content: [{ type: 'text', text: await handleDescribeModel(a.model as string) }] };

      case 'list_routes':
        return { content: [{ type: 'text', text: await handleListRoutes(a.filter as string | undefined) }] };

      case 'db_schema':
        return { content: [{ type: 'text', text: await handleDbSchema(a.table as string | undefined) }] };

      case 'run_pint':
        return { content: [{ type: 'text', text: await handleRunPint(a.fix as boolean | undefined, a.path as string | undefined) }] };

      case 'run_phpstan':
        return { content: [{ type: 'text', text: await handleRunPhpstan(a.path as string | undefined) }] };

      case 'run_tests':
        return {
          content: [{
            type: 'text',
            text: await handleRunTests(
              a.file as string | undefined,
              a.filter as string | undefined,
              a.suite as 'Feature' | 'Unit' | undefined
            ),
          }],
        };

      case 'artisan_command':
        return { content: [{ type: 'text', text: await handleArtisanCommand(a.command as string) }] };

      case 'get_security_context':
        return { content: [{ type: 'text', text: await handleGetSecurityContext(a.topic as string | undefined) }] };

      case 'environment_health':
        return { content: [{ type: 'text', text: await handleEnvironmentHealth(a.check as string | undefined) }] };

      default:
        return { content: [{ type: 'text', text: JSON.stringify({ error: `Unknown tool: ${name}` }) }] };
    }
  } catch (err: unknown) {
    const msg = err instanceof Error ? err.message : String(err);
    return { content: [{ type: 'text', text: JSON.stringify({ success: false, error: msg }) }] };
  }
});

const transport = new StdioServerTransport();
await server.connect(transport);
