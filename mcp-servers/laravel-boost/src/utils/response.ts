export type DataSource = 'docker' | 'files' | 'cache' | 'mixed';

export interface ToolResponse<T = unknown> {
  success: boolean;
  source: DataSource;
  cached: boolean;
  timestamp: string;
  data: T;
  warnings?: string[];
  error?: string;
  metadata?: {
    executionMs: number;
    containerStatus?: 'running' | 'stopped' | 'unknown';
  };
}

export function buildResponse<T>(
  data: T,
  opts: {
    source: DataSource;
    cached?: boolean;
    warnings?: string[];
    startMs?: number;
    containerStatus?: 'running' | 'stopped' | 'unknown';
  }
): ToolResponse<T> {
  const executionMs = opts.startMs !== undefined ? Date.now() - opts.startMs : 0;
  const response: ToolResponse<T> = {
    success: true,
    source: opts.source,
    cached: opts.cached ?? false,
    timestamp: new Date().toISOString(),
    data,
  };
  if (opts.warnings && opts.warnings.length > 0) {
    response.warnings = opts.warnings;
  }
  response.metadata = { executionMs };
  if (opts.containerStatus) {
    response.metadata.containerStatus = opts.containerStatus;
  }
  return response;
}

export function buildError(
  message: string,
  opts: {
    source?: DataSource;
    startMs?: number;
  } = {}
): ToolResponse<null> {
  return {
    success: false,
    source: opts.source ?? 'files',
    cached: false,
    timestamp: new Date().toISOString(),
    data: null,
    error: message,
    metadata: {
      executionMs: opts.startMs !== undefined ? Date.now() - opts.startMs : 0,
    },
  };
}

export function responseToText(res: ToolResponse): string {
  return JSON.stringify(res, null, 2);
}
