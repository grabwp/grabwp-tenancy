# Marketing Assets & Brand Materials Audit

## Findings

**Status:** Limited marketing assets — needs development.

### Existing Materials

**1. Brand Identity**
- **Website**: grabwp.com (external, not in repo)
- **WordPress.org Listing**: Plugin Directory
- **Social/Community**: GitHub, WordPress.org Forum
- No on-repo brand guidelines document

**2. Design Standards**
- `/docs/design-guidelines.md` (2.3K) - WordPress admin UI guidance ONLY
  - Covers admin interface conventions, not brand/marketing
  - References: form patterns, notices, buttons, accessibility
  - No color palette, typography, or brand voice defined

**3. Color Scheme**
- Admin CSS (`/admin/css/grabwp-admin.css`):
  - Active status: `#46b450` (green)
  - Inactive status: `#dc3232` (red)
  - Minimal custom styling; relies on WordPress defaults
- No comprehensive color palette documented

**4. Visual Assets**
- **Zero native marketing assets** in repo
- No logos, icons, or banners in project root or `/assets/`
- No `/marketing/`, `/images/`, or `/brand/` directories
- Asset images in `/node_modules/` and `.venv/` (dependencies only)

**5. Branding References**
- README mentions: grabwp.com, WordPress.org, GitHub
- No social media profiles listed in code
- No email/contact addresses for support (external-only)

### Gap Analysis

Missing:
- Brand guidelines document
- Logo files (primary/secondary variants)
- Color palette spec with codes
- Typography system
- Marketing collateral templates
- Social media guidelines
- Email signature/banner templates

## Recommendation

Create `/docs/brand-guidelines.md` covering:
1. Logo usage (asset links)
2. Color palette with hex codes
3. Typography (font stacks)
4. Tone of voice (marketing copy style)
5. Social media profiles

**Next Phase**: Develop marketing assets alongside pro feature expansion.
