# TYPO3 Extension Migration Plan: XML API to OpenAPI

## Executive Summary
This document outlines the migration strategy for transitioning the TYPO3 Extension from Elsevier Pure's XML-based SOAP/REST API to the modern OpenAPI (REST) specification. The migration will improve performance, maintainability, and align with current API standards.

## Current State Analysis

### Existing API Implementation
- **Protocol**: XML-based POST/GET requests
- **Base Path**: `/ws/api/` endpoints  
- **Authentication**: API key in headers
- **Response Format**: XML/JSON hybrid (XML requests, JSON responses)
- **Caching**: 4-hour cache lifetime with FrontendInterface

### Endpoints Currently in Use
1. **persons** - Personnel data and profiles
2. **research-outputs** - Publications and research results
3. **projects** - Research projects
4. **organisational-units** - Organizational structure
5. **data-sets** - Research datasets
6. **equipments** - Research equipment

## OpenAPI Migration Strategy

### Phase 1: Infrastructure Setup (Week 1-2)

#### 1.1 Create New Service Layer
```
Classes/
├── Service/
│   ├── OpenApi/
│   │   ├── OpenApiClient.php         # New REST client
│   │   ├── OpenApiAuthenticator.php  # OAuth/Bearer token handler
│   │   └── OpenApiResponseParser.php # JSON response handler
│   └── WebService.php                # Existing (to be deprecated)
```

#### 1.2 Environment Configuration
- Add new configuration variables:
  - `PURE_API_VERSION`: 'openapi' or 'xml' (for gradual migration)
  - `PURE_OPENAPI_URL`: New API base URL
  - `PURE_OAUTH_CLIENT_ID`: OAuth client ID (if required)
  - `PURE_OAUTH_CLIENT_SECRET`: OAuth secret (if required)

#### 1.3 Response Model Classes
Create typed response models for each endpoint:
```
Classes/Domain/Model/Api/
├── Person.php
├── ResearchOutput.php
├── Project.php
├── Organisation.php
├── DataSet.php
└── Equipment.php
```

### Phase 2: Endpoint Migration (Week 3-6)

#### 2.1 WebService Adapter Pattern
Implement adapter pattern to support both APIs during transition:

```php
interface ApiServiceInterface {
    public function getPersons(array $params): array;
    public function getResearchOutputs(array $params): array;
    public function getProjects(array $params): array;
}

class XmlApiService implements ApiServiceInterface { /* existing */ }
class OpenApiService implements ApiServiceInterface { /* new */ }
class ApiServiceFactory {
    public function create(): ApiServiceInterface {
        return $this->config['api_version'] === 'openapi' 
            ? new OpenApiService() 
            : new XmlApiService();
    }
}
```

#### 2.2 Endpoint Mapping

| Current XML Endpoint | OpenAPI Endpoint | Method | Notes |
|---------------------|------------------|--------|-------|
| POST /ws/api/persons | GET /api/v1/persons | GET | Query params instead of XML body |
| POST /ws/api/research-outputs | GET /api/v1/research-outputs | GET | Pagination via offset/limit |
| POST /ws/api/projects | GET /api/v1/projects | GET | Filter params in query string |
| POST /ws/api/organisational-units | GET /api/v1/organizations | GET | Renamed endpoint |
| GET /ws/api/persons/{uuid} | GET /api/v1/persons/{id} | GET | Direct ID access |
| GET /ws/api/projects/{uuid} | GET /api/v1/projects/{id} | GET | Direct ID access |

#### 2.3 Query Parameter Mapping

**XML Query Structure → OpenAPI Parameters:**

```xml
<!-- Current XML -->
<personsQuery>
    <searchString>term</searchString>
    <size>20</size>
    <offset>0</offset>
    <orderings><ordering>-startDate</ordering></orderings>
</personsQuery>
```

```http
# New OpenAPI
GET /api/v1/persons?q=term&limit=20&offset=0&sort=-startDate
```

### Phase 3: Feature Parity Implementation (Week 7-9)

#### 3.1 Complex Query Migration
- **Nested organization queries**: Use `expand` parameter
- **Person associations**: Use `include` parameter  
- **Date range filters**: Use ISO 8601 format
- **Locale handling**: Use `Accept-Language` header

#### 3.2 Rendering Templates
- Replace XML rendering instructions with view selection:
  - `?view=short` instead of `<rendering>short</rendering>`
  - `?view=detailed` instead of `<rendering>detailsPortal</rendering>`
  - `?format=bibtex` for special formats

#### 3.3 Pagination Strategy
```php
// Old: Calculate offset manually
$offset = ($page - 1) * $pageSize;

// New: Use standard pagination
$params = [
    'page' => $page,
    'per_page' => $pageSize,
    'sort' => $ordering
];
```

### Phase 4: Testing & Validation (Week 10-11)

#### 4.1 Test Coverage
- Unit tests for new OpenApiClient
- Integration tests for each endpoint
- Regression tests comparing XML vs OpenAPI responses
- Performance benchmarks

#### 4.2 Data Validation
Create validation suite to ensure:
- All UUIDs map correctly
- Rendering output matches
- Search results are consistent
- Pagination works correctly

### Phase 5: Deployment Strategy (Week 12)

#### 5.1 Feature Flag Rollout
```php
class FeatureToggle {
    public function isOpenApiEnabled(): bool {
        return (bool) $this->config['features']['openapi_enabled'] ?? false;
    }
}
```

#### 5.2 Gradual Migration Path
1. **Stage 1**: Read-only endpoints (GET requests)
2. **Stage 2**: Search and filter operations  
3. **Stage 3**: Complex queries and associations
4. **Stage 4**: Full deprecation of XML API

#### 5.3 Rollback Plan
- Keep XML implementation for 6 months post-migration
- Monitor error rates and performance metrics
- One-click rollback via configuration flag

## Technical Considerations

### Authentication Changes
- Migrate from API key to Bearer token/OAuth 2.0
- Implement token refresh mechanism
- Store tokens securely in TYPO3 registry

### Caching Strategy
- Maintain existing 4-hour cache lifetime
- Add ETag support for conditional requests
- Implement cache warming for frequently accessed data

### Error Handling
```php
class OpenApiErrorHandler {
    public function handle(\Throwable $e): void {
        match($e->getCode()) {
            400 => throw new BadRequestException(),
            401 => $this->refreshTokenAndRetry(),
            404 => throw new NotFoundException(),
            429 => $this->handleRateLimit($e),
            default => $this->logAndFallback($e)
        };
    }
}
```

### Performance Optimizations
- Implement request batching where possible
- Use HTTP/2 multiplexing
- Add request/response compression
- Optimize JSON parsing with streaming

## Breaking Changes & Compatibility

### Deprecated Features
- XML request building methods
- Manual pagination calculation
- String-based field selection

### Migration Helpers
```php
class MigrationHelper {
    public function convertXmlQueryToParams(string $xml): array {
        // Parse XML and return OpenAPI parameters
    }
    
    public function mapLegacyFields(array $fields): array {
        // Map old field names to new structure
    }
}
```

## Monitoring & Metrics

### Key Performance Indicators
- API response time (target: <200ms)
- Cache hit ratio (target: >80%)
- Error rate (target: <0.1%)
- Migration completion rate

### Logging Strategy
```php
$this->logger->info('OpenAPI request', [
    'endpoint' => $endpoint,
    'params' => $params,
    'response_time' => $responseTime,
    'cache_hit' => $cacheHit
]);
```

## Timeline & Milestones

| Phase | Duration | Deliverable |
|-------|----------|-------------|
| Phase 1 | 2 weeks | Infrastructure ready |
| Phase 2 | 4 weeks | Core endpoints migrated |
| Phase 3 | 3 weeks | Feature parity achieved |
| Phase 4 | 2 weeks | Testing complete |
| Phase 5 | 1 week | Production deployment |
| **Total** | **12 weeks** | **Full migration** |

## Risk Assessment

### High Risk Items
1. **Data structure changes**: OpenAPI may return different data structures
2. **Authentication complexity**: OAuth implementation may require additional infrastructure
3. **Performance degradation**: New API might have different rate limits

### Mitigation Strategies
- Comprehensive data mapping layer
- Fallback to XML API on errors
- Request queuing and retry logic
- Extensive monitoring and alerting

## Post-Migration Tasks

1. **Documentation Update**
   - Update README with new configuration
   - Create migration guide for other installations
   - Document new API features

2. **Code Cleanup**
   - Remove XML-specific code (after stabilization period)
   - Refactor endpoint classes for OpenAPI patterns
   - Update type hints and PHPDoc

3. **Performance Tuning**
   - Analyze query patterns
   - Optimize frequently used endpoints
   - Implement predictive caching

## Conclusion

This migration plan provides a structured approach to transitioning from XML to OpenAPI while maintaining system stability and data integrity. The phased approach allows for gradual migration with minimal disruption to existing functionality.

## Appendix A: Configuration Example

```env
# .env file changes
PURE_API_VERSION=openapi
PURE_OPENAPI_URL=https://api.pure.elsevier.com/v1
PURE_OAUTH_CLIENT_ID=your-client-id
PURE_OAUTH_CLIENT_SECRET=your-client-secret
PURE_API_TIMEOUT=30
PURE_API_RETRY_ATTEMPTS=3
```

## Appendix B: Sample Code Migrations

### Before (XML):
```php
$xml = '<?xml version="1.0"?>
<personsQuery>
    <searchString>' . $searchTerm . '</searchString>
    <size>20</size>
    <fields>name,email</fields>
</personsQuery>';
$result = $this->webService->getJson('persons', $xml);
```

### After (OpenAPI):
```php
$result = $this->openApiClient->get('/persons', [
    'q' => $searchTerm,
    'limit' => 20,
    'fields' => 'name,email'
]);
```

## Appendix C: Testing Checklist

- [ ] All endpoints return expected data structure
- [ ] Search functionality works correctly
- [ ] Pagination maintains consistency
- [ ] Sorting orders are preserved
- [ ] Filter combinations work as expected
- [ ] Cache invalidation triggers properly
- [ ] Error handling covers all status codes
- [ ] Performance meets or exceeds current metrics
- [ ] Backward compatibility maintained during transition
- [ ] Documentation is complete and accurate
