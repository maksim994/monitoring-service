# Probe Checks

## Probe Node

Probe node is stateless and executes external checks assigned by scheduler. It does not store site secrets.

## HTTP Check

Settings:

- URL;
- method: `GET` or `HEAD`;
- timeout;
- follow redirects;
- allowed status codes;
- expected text;
- headers;
- user agent.

Default:

- method: `GET`;
- timeout: 10 seconds;
- allowed status: `200-399`;
- redirects: disabled for MVP unless explicitly enabled.

## SSL Check

Checks:

- certificate expiry;
- CN/SAN hostname match;
- chain validity;
- handshake error;
- protocol error.

## DNS And Domain Check

MVP:

- A/AAAA resolve;
- NXDOMAIN/SERVFAIL;
- domain expiry through WHOIS/RDAP where available.

Domain expiry is best-effort and should not block MVP readiness.

## Probe Result Contract

```json
{
  "probeId": "eu-1",
  "checkId": "check_123",
  "status": "failed",
  "httpCode": 500,
  "responseTimeMs": 380,
  "errorCode": null,
  "errorMessage": null,
  "evidence": {
    "url": "https://example.ru/"
  }
}
```

## Safety

Every URL must pass SSRF protection from `../security/ssrf-protection.md`.
