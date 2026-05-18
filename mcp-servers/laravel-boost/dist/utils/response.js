export function buildResponse(data, opts) {
    const executionMs = opts.startMs !== undefined ? Date.now() - opts.startMs : 0;
    const response = {
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
export function buildError(message, opts = {}) {
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
export function responseToText(res) {
    return JSON.stringify(res, null, 2);
}
//# sourceMappingURL=response.js.map