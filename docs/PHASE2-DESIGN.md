# Phase 2 design decisions

Read plan pp.7–8 and design 00 (p.2), S01–S03 (pp.4–6), M01–M08 (pp.42–49), D01 (p.81). A02 p.51 is used only as a shell/fixture layout reference, not a completed operational dashboard.

The working project was moved by the user to Desktop/ELVARQO. Phase 1 source and uncommitted work were retained; files changed in this phase were backed up under the chat workspace work/phase2-before before editing.

## Shared implementation
shared/ui/ is the canonical component and token source. shared/sync-ui.mjs copies identical files into each React app's src/ui before dev, build, check and format. This keeps both independently installable apps using one source without React peer-resolution problems or a new workspace lockfile. Edit canonical files, never generated app copies; restart dev after shared-source edits or run npm run sync-ui. App-specific compositions stay in each app. All existing dependency locks remain; no new production package is required.

Tokens retain #202722 / #F6F5EF / #475640 / #B8A17B, an 8px spacing rhythm, editorial Georgia and functional Arial as system-font substitutes pending approved brand fonts. Breakpoints: 600px compact admin, 768px mobile content, 900px collapsed navigation, 1200px narrower admin, 1440px max content.

Shared controls: buttons, visible-label inputs/selects, inline validation, badges, native-modal dialogs/drawers, product cards/grid, loading skeleton, empty/error examples, responsive table and pagination. Native dialog showModal provides background inertness; an explicit Tab/Shift+Tab loop maintains focus containment even at the browser chrome boundary. Escape/backdrop/close work and cleanup returns focus to the trigger. Body scroll locks while open. Action controls target at least 44px touch height; form inputs are 16px. Reduced motion removes animations/transitions.

## Assets, data and scope
The four JPEGs in storefront/public/concept were extracted from the supplied design PDF pages 4–5. These are generated design illustrations, not real product photography. Persistent preview notices, image labels and fixture-specific descriptions prevent them being presented as inventory. No claims, reviews or delivery promises are invented.

Typed fixtures are isolated in src/fixtures in each app. Admin values are explicitly marked demonstration-only. Backend health clients remain in lib/api and diagnostics move to /development (storefront) and ?view=development (admin). No backend, database, credentials or API business contract changes are needed.

## Routes and deliberate scope decisions
Storefront / is the campaign/discovery shell; /collections previews the existing fixture cards, not live catalogue filtering. Product preview opens a modal with unavailable purchase action. Navigation links to static placeholders for the agreed editorial/help/policy routes. /design-system exposes the control gallery. The storefront shell and meaningful page text pre-render into HTML; all preview pages remain noindex before launch.

Admin uses real URL query navigation for its shell (?view=orders etc.), with honest placeholders for unimplemented operations. The overview is a labelled visual fixture, not live reporting. Search filters demonstration orders locally; date switching shows a meaningful empty fixture state. No staff identities, access controls or record writes are faked.

M01/M08 inform shell reflow. M02–M07 inform reusable mobile controls only; full shopping, checkout, account and fulfilment screens remain in their assigned phases. No forms send data: newsletter signup is visibly unavailable; UI reference validation is local only. Sharing, authentication, commerce and production deployment are outside Phase 2.

Deliberate differences from the PDF: real empty bag count 0 replaces illustrative count 2; system fonts stand in for unspecified font files; preview notices are added; hero stacks on mobile for readable type rather than placing text over the image; admin adds clear fixture labels and diagnostics links. All differences protect clarity while retaining approved visual direction.


## Native zoom verification
Supplemental test uses an isolated Playwright Chromium persistent profile and a test-only tabs extension, sets chrome.tabs.setZoom(tabId, 2), verifies getZoom=2 and checks document reflow. It does not modify the user's browser. Both apps passed native 200% zoom (1440 -> 720 CSS px, scroll width=720). The base Edge suite separately verifies equivalent-viewport reflow. Reference APIs: https://developer.chrome.com/docs/extensions/reference/api/tabs and https://playwright.dev/docs/chrome-extensions .
