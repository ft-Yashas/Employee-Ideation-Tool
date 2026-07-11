/**
 * Wraps an async route handler so any thrown/rejected error is forwarded to
 * Express's error middleware instead of crashing the process. Lets controllers
 * use plain `async/await` and `throw new ApiError(...)`.
 */
export const asyncHandler = (fn) => (req, res, next) =>
  Promise.resolve(fn(req, res, next)).catch(next);

export default asyncHandler;
