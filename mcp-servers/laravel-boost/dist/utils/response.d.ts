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
export declare function buildResponse<T>(data: T, opts: {
    source: DataSource;
    cached?: boolean;
    warnings?: string[];
    startMs?: number;
    containerStatus?: 'running' | 'stopped' | 'unknown';
}): ToolResponse<T>;
export declare function buildError(message: string, opts?: {
    source?: DataSource;
    startMs?: number;
}): ToolResponse<null>;
export declare function responseToText(res: ToolResponse): string;
//# sourceMappingURL=response.d.ts.map