export interface ExecResult {
    stdout: string;
    stderr: string;
    exitCode: number;
}
export declare function isContainerRunning(container?: string): boolean;
export declare function invalidateContainerCache(): void;
export declare function execInDocker(cmd: string, container?: string): ExecResult;
export declare function execLocal(cmd: string, cwd?: string): ExecResult;
export declare function dockerNotRunningError(container?: string): string;
//# sourceMappingURL=exec.d.ts.map