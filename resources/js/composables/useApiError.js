/**
 * Pull the server's actual error message out of an Axios error, whether the
 * response body is JSON (the common case) or a Blob — file-download requests
 * set `responseType: 'blob'`, so even a JSON error body from a failed
 * download arrives as a Blob instead of a parsed object. Falls back to the
 * given default when the server didn't send a message at all (e.g. a
 * network failure with no response).
 */
export async function extractErrorMessage(err, fallback) {
  const data = err?.response?.data;

  if (data instanceof Blob) {
    try {
      const text = await data.text();
      return JSON.parse(text).message ?? fallback;
    } catch {
      return fallback;
    }
  }

  return data?.message ?? fallback;
}
