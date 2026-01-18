<objective>
Research and compare three routing approaches for adding archive/single page support to MakermakerCore resources. The goal is public-facing URLs like `/{pluralized-resource}/` (archive) and `/{pluralized-resource}/{id}/` (single) that match the REST API's inflection logic.

Controller-based rendering decided. This prompt explores ROUTING mechanisms only.
</objective>

<context>
**Current State:**
- REST API uses `ReflectiveRestWrapper` hooking `parse_request` (priority 5)
- URL pattern: `tr-api/rest/{resource}/{id?}/actions/{action?}`
- Pluralization via `StringHelper::pluralize()` using TypeRocket's `Inflect` class
- Resources registered via `Registry::addCustomResource($slug, ['controller' => $fqcn])`
- No existing frontend routing - REST only

**Desired Outcome:**
- `GET /departments/` → DepartmentController::archive() or similar
- `GET /departments/5/` → DepartmentController::single($id)
- URLs use same pluralized slug as REST API
- Controller renders view (no template file hierarchy)

@vendor/mxcro/makermaker-core/src/Rest/ReflectiveRestWrapper.php
@vendor/mxcro/makermaker-core/src/Helpers/StringHelper.php
@vendor/mxcro/makermaker-core/src/Helpers/ResourceHelper.php
</context>

<research_requirements>

## Option A: WordPress Rewrite API

Explore using `add_rewrite_rule()`, `add_rewrite_tag()`, `flush_rewrite_rules()`:

1. How would rules be registered for each resource?
2. How to hook `template_redirect` or `template_include` to dispatch to controller?
3. Pros: native WP, plays nice with existing permalinks
4. Cons: flush requirements, complexity with custom query vars
5. Code sketch for registration and dispatch

## Option B: TypeRocket Routes

Explore extending TR's routing if applicable:

1. Does TypeRocket have frontend routing beyond admin?
2. Can `Routes::resource()` or custom routes work for public URLs?
3. Integration with existing parse_request hook
4. Pros/cons vs WP rewrites
5. Code sketch if viable

## Option C: Hybrid (WP rewrites + custom dispatch)

Explore using WP rewrite for URL matching, custom handler for dispatch:

1. Similar to current REST approach but for frontend
2. Add rewrite rules that set query vars
3. Hook `parse_request` or `template_redirect` to intercept and dispatch
4. Reuse existing controller resolution logic from ReflectiveRestWrapper
5. Code sketch

## Comparison Matrix

For each option, evaluate:
- Setup complexity
- Performance implications
- Compatibility with WP ecosystem (caching, SEO plugins)
- Maintainability
- Conflict potential with themes/plugins
</research_requirements>

<output_format>
Save findings to: `./research/frontend-routing-options.md`

Structure:
1. Executive summary (which option recommended and why)
2. Option A detailed analysis with code sketch
3. Option B detailed analysis with code sketch
4. Option C detailed analysis with code sketch
5. Comparison matrix table
6. Recommended implementation approach
7. Unresolved questions
</output_format>

<verification>
Before completing:
- All three options have working code sketches
- Pros/cons clearly articulated for each
- Clear recommendation with rationale
- Integration points with existing MakermakerCore identified
</verification>
