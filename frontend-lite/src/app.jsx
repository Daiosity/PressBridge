const { useEffect, useMemo, useState } = React;

const API_BASE = "http://pressbridge.local/wp-json/pressbridge/v1";

async function apiFetch(path) {
  let response;

  try {
    response = await fetch(`${API_BASE}${path}`, {
      headers: {
        Accept: "application/json"
      }
    });
  } catch (networkError) {
    throw new Error(
      "Unable to reach the PressBridge API. Confirm WordPress is running, the plugin is active, and the API base URL is correct."
    );
  }

  if (!response.ok) {
    let message = "Request failed.";

    try {
      const error = await response.json();
      message = error.message || message;
    } catch (parseError) {
      message = response.statusText || message;
    }

    const error = new Error(message);
    error.status = response.status;
    throw error;
  }

  try {
    return await response.json();
  } catch (parseError) {
    const error = new Error("The PressBridge API returned an invalid JSON response.");
    error.status = response.status;
    throw error;
  }
}

function navigate(path) {
  window.history.pushState({}, "", path);
  window.dispatchEvent(new PopStateEvent("popstate"));
}

function AppLink({ href, children, className = "", target = "" }) {
  return (
    <a
      href={href}
      className={className}
      target={target || undefined}
      rel={target === "_blank" ? "noreferrer" : undefined}
      onClick={(event) => {
        const url = new URL(href, window.location.origin);

        if (target === "_blank" || url.origin !== window.location.origin) {
          return;
        }

        event.preventDefault();
        navigate(url.pathname + url.search);
      }}
    >
      {children}
    </a>
  );
}

function getSiteOrigin(siteHomeUrl) {
  try {
    return new URL(siteHomeUrl).origin;
  } catch (error) {
    return "";
  }
}

function getMenuLink(item, siteHomeUrl) {
  if (!item?.url) {
    return { href: "/", internal: true, target: "" };
  }

  try {
    const siteOrigin = getSiteOrigin(siteHomeUrl);
    const resolved = new URL(item.url, siteHomeUrl || window.location.origin);
    const internal =
      resolved.origin === window.location.origin ||
      (siteOrigin && resolved.origin === siteOrigin);

    return {
      href: internal ? `${resolved.pathname}${resolved.search}` || "/" : resolved.toString(),
      internal,
      target: item.target || ""
    };
  } catch (error) {
    return {
      href: item.url || "/",
      internal: false,
      target: item.target || ""
    };
  }
}

function getPreferredMenu(menus, preferredLocations = []) {
  const locations = menus?.locations || {};

  for (const location of preferredLocations) {
    if (locations[location]?.menu?.items?.length) {
      return locations[location].menu;
    }
  }

  const firstAssigned = Object.values(locations).find((entry) => entry?.menu?.items?.length);
  return firstAssigned ? firstAssigned.menu : null;
}

function buildFallbackLinks(pages, posts) {
  const links = [{ id: "home", title: "Home", href: "/" }];

  if (posts.length) {
    links.push({ id: "blog", title: "Blog", href: "/" });
  }

  pages.slice(0, 4).forEach((page) => {
    links.push({ id: `page-${page.id}`, title: page.title, href: page.path });
  });

  return links.filter(
    (item, index, items) => items.findIndex((candidate) => candidate.href === item.href) === index
  );
}

function describeRouteMode(site) {
  if (!site?.headless_mode) {
    return "WordPress safe mode";
  }

  return site.route_handling_mode === "redirect"
    ? "Redirect handoff enabled"
    : "WordPress safe mode";
}

function MenuAnchor({ item, siteHomeUrl, className = "nav-link" }) {
  const link = getMenuLink(item, siteHomeUrl);

  return (
    <AppLink
      className={className}
      href={link.href}
      target={link.internal ? "" : link.target || "_blank"}
    >
      {item.title}
    </AppLink>
  );
}

function MenuItem({ item, siteHomeUrl }) {
  const hasChildren = Boolean(item.children?.length);

  return (
    <li className={`nav-item${hasChildren ? " has-children" : ""}`}>
      <MenuAnchor item={item} siteHomeUrl={siteHomeUrl} />
      {hasChildren ? (
        <ul className="submenu">
          {item.children.map((child) => (
            <MenuItem key={child.id} item={child} siteHomeUrl={siteHomeUrl} />
          ))}
        </ul>
      ) : null}
    </li>
  );
}

function FallbackNavigation({ items }) {
  return (
    <ul className="nav-list">
      {items.map((item) => (
        <li key={item.id} className="nav-item">
          <AppLink className="nav-link" href={item.href}>
            {item.title}
          </AppLink>
        </li>
      ))}
    </ul>
  );
}

function Navigation({ menus, siteHomeUrl, pages, posts }) {
  const primaryMenu = getPreferredMenu(menus, ["primary", "header", "main", "menu-1"]);
  const fallbackLinks = buildFallbackLinks(pages, posts);

  return (
    <nav className="site-nav" aria-label="Primary navigation">
      {primaryMenu?.items?.length ? (
        <ul className="nav-list">
          {primaryMenu.items.map((item) => (
            <MenuItem key={item.id} item={item} siteHomeUrl={siteHomeUrl} />
          ))}
        </ul>
      ) : (
        <FallbackNavigation items={fallbackLinks} />
      )}
    </nav>
  );
}

function FooterNavigation({ menus, siteHomeUrl, pages, posts }) {
  const footerMenu = getPreferredMenu(menus, ["footer", "secondary", "footer-menu", "menu-2"]);
  const fallbackLinks = buildFallbackLinks(pages, posts).slice(0, 5);

  if (footerMenu?.items?.length) {
    return (
      <ul className="footer-links">
        {footerMenu.items.map((item) => (
          <li key={item.id}>
            <MenuAnchor item={item} siteHomeUrl={siteHomeUrl} className="footer-link" />
          </li>
        ))}
      </ul>
    );
  }

  return (
    <ul className="footer-links">
      {fallbackLinks.map((item) => (
        <li key={item.id}>
          <AppLink className="footer-link" href={item.href}>
            {item.title}
          </AppLink>
        </li>
      ))}
    </ul>
  );
}

function Header({ site, menus, pages, posts }) {
  const ctaPage =
    pages.find((page) => ["contact", "about", "sample-page"].includes(page.slug)) || pages[0] || null;

  return (
    <header className="site-header-shell">
      <div className="brand-block">
        <p className="eyebrow">Headless-ready WordPress</p>
        <AppLink className="site-title" href="/">
          PressBridge
        </AppLink>
        <p className="site-description">Connect WordPress to modern frontends.</p>
      </div>

      <div className="header-nav-block">
        <Navigation menus={menus} siteHomeUrl={site?.home_url} pages={pages} posts={posts} />
        {ctaPage ? (
          <AppLink className="button-link button-link-primary header-cta" href={ctaPage.path}>
            {ctaPage.slug === "contact" ? "Contact" : `Explore ${ctaPage.title}`}
          </AppLink>
        ) : null}
      </div>
    </header>
  );
}

function Footer({ site, menus, pages, posts }) {
  return (
    <footer className="site-footer-shell">
      <div className="footer-column">
        <p className="eyebrow">Powered by WordPress</p>
        <h2>PressBridge</h2>
        <p>
          Publish in WordPress, render with React, and keep routing, preview, and safe handoff in
          one bridge layer.
        </p>
      </div>

      <div className="footer-column">
        <p className="eyebrow">Navigation</p>
        <FooterNavigation menus={menus} siteHomeUrl={site?.home_url} pages={pages} posts={posts} />
      </div>

      <div className="footer-column">
        <p className="eyebrow">Stack</p>
        <ul className="stack-list">
          <li>WordPress for content and editorial workflows</li>
          <li>PressBridge for route handoff, previews, and normalized API responses</li>
          <li>React for the public rendering layer</li>
        </ul>
      </div>
    </footer>
  );
}

function HeroSection({ site, pages, posts }) {
  const primaryPage =
    pages.find((page) => ["sample-page", "about", "contact"].includes(page.slug)) || pages[0] || null;
  const primaryPost = posts[0] || null;

  return (
    <section className="hero-panel">
      <div className="hero-copy">
        <p className="eyebrow">Release-ready alpha</p>
        <h1>Same WordPress content. Better frontend.</h1>
        <p className="hero-lede">
          PressBridge lets you keep WordPress as the CMS while moving the public experience to
          React without breaking wp-admin, previews, or editorial workflows.
        </p>
        <div className="action-row">
          {primaryPage ? (
            <AppLink className="button-link button-link-primary" href={primaryPage.path}>
              Open sample page
            </AppLink>
          ) : null}
          {primaryPost ? (
            <AppLink className="button-link" href={primaryPost.path}>
              Read latest post
            </AppLink>
          ) : null}
        </div>
        <p className="inline-note">
          This page is using live WordPress content and routes, but it is being rendered by React
          through PressBridge.
        </p>
      </div>

      <div className="hero-proof">
        <div className="proof-card">
          <span className="proof-label">Content source</span>
          <strong>{site?.name || "WordPress site"}</strong>
        </div>
        <div className="proof-card">
          <span className="proof-label">Frontend target</span>
          <strong>{site?.frontend_url || "Not configured"}</strong>
        </div>
        <div className="proof-card">
          <span className="proof-label">Delivery mode</span>
          <strong>{describeRouteMode(site)}</strong>
        </div>
      </div>
    </section>
  );
}

function SystemOverview({ site }) {
  const items = [
    {
      label: "Content managed in",
      value: "WordPress admin",
      copy: "Pages, posts, menus, and previews remain in WordPress."
    },
    {
      label: "Frontend rendered by",
      value: "React",
      copy: "Public routes are rendered in a modern component-based frontend."
    },
    {
      label: "Delivery mode",
      value: describeRouteMode(site),
      copy:
        site?.route_handling_mode === "redirect"
          ? "Logged-out public requests can be handed off to React."
          : "WordPress is still serving public pages while the frontend is integrated."
    },
    {
      label: "Preview workflow",
      value: site?.frontend_url ? "Signed preview links" : "Needs frontend URL",
      copy: "Editors can review frontend previews without leaving WordPress."
    }
  ];

  return (
    <section className="content-card">
      <p className="eyebrow">System overview</p>
      <h2>How this PressBridge build is wired</h2>
      <div className="overview-grid">
        {items.map((item) => (
          <article key={item.label} className="overview-card">
            <p className="overview-label">{item.label}</p>
            <h3>{item.value}</h3>
            <p>{item.copy}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function BenefitGrid() {
  const items = [
    {
      title: "Keep WordPress workflows",
      copy: "Editors keep using the familiar WordPress admin for content, menus, publishing, and previews."
    },
    {
      title: "Modern frontend freedom",
      copy: "Build the public experience in React instead of forcing modern UX work back into PHP templates."
    },
    {
      title: "Safe gradual adoption",
      copy: "Start with WordPress rendering, then turn on redirect handoff only when the frontend is ready."
    }
  ];

  return (
    <section className="content-card">
      <p className="eyebrow">Why PressBridge</p>
      <h2>Use WordPress as the backend without losing frontend control</h2>
      <div className="advantage-grid">
        {items.map((item) => (
          <article key={item.title} className="advantage-card">
            <h3>{item.title}</h3>
            <p>{item.copy}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function LiveProof({ site, route }) {
  return (
    <section className="content-card">
      <p className="eyebrow">Live proof</p>
      <h2>The content is still WordPress. The presentation is React.</h2>
      <div className="live-proof-layout">
        <p className="lede">
          The route, content source, and editorial flow still come from WordPress. PressBridge
          resolves the route and hands the same content to React so the frontend can present it
          differently.
        </p>
        <ul className="proof-list">
          <li>
            <strong>Route source:</strong> {route?.path || "/"} resolved by WordPress
          </li>
          <li>
            <strong>Editorial flow:</strong> publishing and previews still start in WordPress
          </li>
          <li>
            <strong>Content source:</strong> {site?.home_url || "WordPress"} still supplies the live data
          </li>
        </ul>
      </div>
    </section>
  );
}

function RouteExamples({ pages, posts, route }) {
  const samplePage =
    pages.find((page) => ["sample-page", "about", "contact"].includes(page.slug)) || pages[0] || null;
  const latestPost = posts[0] || null;
  const archiveItemCount = route?.totalItems || route?.items?.length || 0;

  return (
    <section className="content-card">
      <p className="eyebrow">Route examples</p>
      <h2>These are real WordPress routes, rendered through the React frontend</h2>
      <div className="archive-grid">
        {samplePage ? (
          <article key={samplePage.id} className="archive-card">
            <p className="eyebrow">Page</p>
            <h3>
              <AppLink href={samplePage.path}>{samplePage.title}</AppLink>
            </h3>
            <p>
              {samplePage.excerpt ||
                "This published WordPress page is being resolved and rendered through the frontend."}
            </p>
            <div className="action-row">
              <AppLink className="button-link" href={samplePage.path}>
                Open page
              </AppLink>
            </div>
          </article>
        ) : null}
        {latestPost ? (
          <article key={latestPost.id} className="archive-card">
            <p className="eyebrow">Latest post</p>
            <h3>
              <AppLink href={latestPost.path}>{latestPost.title}</AppLink>
            </h3>
            <p>{latestPost.excerpt || "The latest published post is already available through the React frontend."}</p>
            <div className="action-row">
              <AppLink className="button-link" href={latestPost.path}>
                Read post
              </AppLink>
            </div>
          </article>
        ) : null}
        <article className="archive-card">
          <p className="eyebrow">Archive route</p>
          <h3>{route?.title || "Blog archive"}</h3>
          <p>
            The homepage route is being resolved as a WordPress archive with {archiveItemCount} published
            {archiveItemCount === 1 ? " item" : " items"} available.
          </p>
          <p className="meta-row">Path: {route?.path || "/"}</p>
        </article>
      </div>
    </section>
  );
}

function LatestContentSection({ route }) {
  const items = route?.items || [];

  if (!items.length) {
    return null;
  }

  return (
    <section className="content-card">
      <p className="eyebrow">Latest content</p>
      <h2>Published in WordPress, delivered through PressBridge</h2>
      <div className="archive-grid">
        {items.slice(0, 3).map((item) => (
          <article key={item.id} className="archive-card">
            <p className="eyebrow">{item.post_type_label || item.post_type}</p>
            <h3>
              <AppLink href={item.path}>{item.title}</AppLink>
            </h3>
            {item.excerpt ? <p>{item.excerpt}</p> : null}
          </article>
        ))}
      </div>
    </section>
  );
}

function SystemHealth({ site }) {
  const items = [
    {
      title: "Frontend responding",
      status: "Ready",
      copy: "This page is currently being served from the React frontend."
    },
    {
      title: "Bridge API responding",
      status: "Ready",
      copy: site?.api_base || "PressBridge API available"
    },
    {
      title: "Preview flow",
      status: site?.frontend_url ? "Ready" : "Needs setup",
      copy: site?.frontend_url
        ? "Signed frontend previews can be generated from WordPress."
        : "Add a frontend URL in WordPress before using frontend previews."
    },
    {
      title: "Current handoff mode",
      status: describeRouteMode(site),
      copy:
        site?.route_handling_mode === "redirect"
          ? "Public visitors can be handed off to React."
          : "WordPress is still serving public pages while integration continues."
    }
  ];

  return (
    <section className="content-card">
      <p className="eyebrow">System health</p>
      <h2>Current delivery status</h2>
      <div className="status-grid">
        {items.map((item) => (
          <article key={item.title} className="status-card">
            <span className="status-badge">{item.status}</span>
            <h3>{item.title}</h3>
            <p>{item.copy}</p>
          </article>
        ))}
      </div>
    </section>
  );
}

function blockClasses(block, ...extraClasses) {
  const classes = ["pressbridge-block"];

  if (block?.name) {
    classes.push(`pressbridge-block-${String(block.name).replace(/[\\/]/g, "-")}`);
  }

  if (block?.attrs?.align) {
    classes.push(`align${block.attrs.align}`);
  }

  if (block?.attrs?.className) {
    classes.push(block.attrs.className);
  }

  extraClasses.filter(Boolean).forEach((item) => classes.push(item));

  return classes.join(" ");
}

function getBlockHtml(block) {
  return block?.rendered_html || block?.inner_html || "";
}

function getBlockText(block) {
  const html = getBlockHtml(block);

  if (!html) {
    return "";
  }

  const element = window.document.createElement("div");
  element.innerHTML = html;
  return (element.textContent || element.innerText || "").trim();
}

function parseBlockHtml(block) {
  const html = getBlockHtml(block);

  if (!html) {
    return null;
  }

  const element = window.document.createElement("div");
  element.innerHTML = html;
  return element;
}

function getElementStyleMap(element) {
  const styleText = element?.getAttribute?.("style");

  if (!styleText) {
    return {};
  }

  return styleText
    .split(";")
    .map((entry) => entry.trim())
    .filter(Boolean)
    .reduce((styles, entry) => {
      const [property, ...valueParts] = entry.split(":");

      if (!property || !valueParts.length) {
        return styles;
      }

      styles[property.trim().toLowerCase()] = valueParts.join(":").trim();
      return styles;
    }, {});
}

function normalizeCssValue(value) {
  if (value === null || value === undefined || value === "") {
    return undefined;
  }

  if (typeof value === "number") {
    return `${value}px`;
  }

  if (typeof value === "string") {
    const presetMatch = value.match(/^var:preset\|([^|]+)\|(.+)$/);

    if (presetMatch) {
      const [, category, token] = presetMatch;
      const slug = token.replace(/[|/]/g, "--");

      return `var(--wp--preset--${category}--${slug})`;
    }
  }

  return value;
}

function applyBoxStyle(style, property, value) {
  if (!value) {
    return;
  }

  if (typeof value === "string" || typeof value === "number") {
    const normalized = normalizeCssValue(value);

    if (normalized !== undefined) {
      style[property] = normalized;
    }

    return;
  }

  if (typeof value !== "object") {
    return;
  }

  const map = {
    top: `${property}Top`,
    right: `${property}Right`,
    bottom: `${property}Bottom`,
    left: `${property}Left`
  };

  Object.entries(map).forEach(([key, cssProperty]) => {
    const normalized = normalizeCssValue(value[key]);

    if (normalized !== undefined) {
      style[cssProperty] = normalized;
    }
  });
}

function getSpacingStyle(spacing = {}) {
  if (!spacing || typeof spacing !== "object") {
    return undefined;
  }

  const style = {};

  applyBoxStyle(style, "padding", spacing.padding);
  applyBoxStyle(style, "margin", spacing.margin);

  if (spacing.blockGap !== undefined) {
    if (typeof spacing.blockGap === "string" || typeof spacing.blockGap === "number") {
      const normalized = normalizeCssValue(spacing.blockGap);

      if (normalized !== undefined) {
        style.gap = normalized;
      }
    } else if (typeof spacing.blockGap === "object") {
      const rowGap = normalizeCssValue(spacing.blockGap.top);
      const columnGap = normalizeCssValue(spacing.blockGap.left);

      if (rowGap !== undefined) {
        style.rowGap = rowGap;
      }

      if (columnGap !== undefined) {
        style.columnGap = columnGap;
      }

      if (style.rowGap !== undefined && style.columnGap !== undefined) {
        style.gap = `${style.rowGap} ${style.columnGap}`;
      }
    }
  }

  return Object.keys(style).length ? style : undefined;
}

function getDimensionStyle(dimensions = {}) {
  if (!dimensions || typeof dimensions !== "object") {
    return undefined;
  }

  const style = {};
  const minHeight = normalizeCssValue(dimensions.minHeight);

  if (minHeight !== undefined) {
    style.minHeight = minHeight;
  }

  return Object.keys(style).length ? style : undefined;
}

function getColorStyle(color = {}) {
  if (!color || typeof color !== "object") {
    return undefined;
  }

  const style = {};

  if (color.background) {
    style.backgroundColor = color.background;
  }

  if (color.text) {
    style.color = color.text;
  }

  return Object.keys(style).length ? style : undefined;
}

function getTypographyStyle(block) {
  const typography = block?.attrs?.style?.typography || {};
  const style = {};
  const fontSize = normalizeCssValue(typography.fontSize || block?.attrs?.fontSize);
  const lineHeight = normalizeCssValue(typography.lineHeight);

  if (fontSize !== undefined) {
    style.fontSize = fontSize;
  }

  if (lineHeight !== undefined) {
    style.lineHeight = lineHeight;
  }

  if (typography.fontStyle) {
    style.fontStyle = typography.fontStyle;
  }

  if (typography.fontWeight) {
    style.fontWeight = typography.fontWeight;
  }

  return Object.keys(style).length ? style : undefined;
}

function mergeStyles(...styles) {
  const merged = Object.assign({}, ...styles.filter(Boolean));

  return Object.keys(merged).length ? merged : undefined;
}

function getBlockStyle(block) {
  return mergeStyles(
    getSpacingStyle(block?.attrs?.style?.spacing),
    getDimensionStyle(block?.attrs?.style?.dimensions),
    getColorStyle(block?.attrs?.style?.color),
    getTypographyStyle(block)
  );
}

function getGalleryStyle(block) {
  const style = mergeStyles(getSpacingStyle(block?.attrs?.style?.spacing)) || {};
  const columns = Number(block?.attrs?.columns || 0);

  if (columns > 0) {
    style.gridTemplateColumns = `repeat(${columns}, minmax(0, 1fr))`;
  }

  return Object.keys(style).length ? style : undefined;
}

function getSeparatorStyle(block) {
  const style = mergeStyles(getSpacingStyle(block?.attrs?.style?.spacing)) || {};
  const borderColor =
    block?.attrs?.style?.color?.background ||
    block?.attrs?.style?.color?.text ||
    block?.attrs?.backgroundColor;
  const opacity = block?.attrs?.opacity;

  if (borderColor) {
    style.borderColor = borderColor;
  }

  if (opacity !== undefined) {
    style.opacity = Number(opacity) / 100;
  }

  return Object.keys(style).length ? style : undefined;
}

function normalizeFlexPosition(value) {
  switch (value) {
    case "left":
    case "top":
      return "flex-start";
    case "right":
    case "bottom":
      return "flex-end";
    case "center":
      return "center";
    case "space-between":
      return "space-between";
    case "space-around":
      return "space-around";
    case "space-evenly":
      return "space-evenly";
    default:
      return undefined;
  }
}

function normalizeAlignItems(value) {
  if (value === "stretch") {
    return "stretch";
  }

  return normalizeFlexPosition(value);
}

function getFlexLayoutStyle(layout = {}, block = null) {
  const style = mergeStyles(getSpacingStyle(block?.attrs?.style?.spacing)) || {};
  const isVertical = layout.orientation === "vertical";

  style.display = "flex";
  style.flexDirection = isVertical ? "column" : "row";
  style.flexWrap = isVertical ? "nowrap" : "wrap";

  const justifyContent = normalizeFlexPosition(layout.justifyContent);
  const alignItems = normalizeAlignItems(layout.verticalAlignment || block?.attrs?.verticalAlignment);

  if (justifyContent) {
    style.justifyContent = justifyContent;
  }

  if (alignItems) {
    style.alignItems = alignItems;
  }

  return style;
}

function getColumnStyle(block) {
  const width = normalizeCssValue(block?.attrs?.width);
  const style = mergeStyles(getSpacingStyle(block?.attrs?.style?.spacing)) || {};

  if (width) {
    style.flexBasis = width;
    style.width = width;
  }

  const verticalAlignment = normalizeAlignItems(block?.attrs?.verticalAlignment);

  if (verticalAlignment) {
    style.alignSelf = verticalAlignment;
  }

  if (style.gap || style.rowGap || style.columnGap) {
    style.display = "grid";
  }

  return Object.keys(style).length ? style : undefined;
}

function getColumnsStyle(block) {
  const widths = (block?.inner_blocks || []).map((item) => normalizeCssValue(item?.attrs?.width));
  const spacingStyle = getSpacingStyle(block?.attrs?.style?.spacing);
  const style = mergeStyles(spacingStyle) || {};

  if (widths.some(Boolean)) {
    return {
      ...style,
      gridTemplateColumns: widths.map((item) => item || "minmax(0, 1fr)").join(" ")
    };
  }

  return {
    ...style,
    "--pressbridge-grid-columns": String(Math.max(1, block?.inner_blocks?.length || 1))
  };
}

function getGroupInnerLayout(block) {
  const layout = block?.attrs?.layout || {};
  const classes = ["pressbridge-group__inner"];
  const style = mergeStyles(getSpacingStyle(block?.attrs?.style?.spacing)) || {};

  if (layout.type === "grid") {
    classes.push("pressbridge-layout-grid");

    if (layout.minimumColumnWidth) {
      style.gridTemplateColumns = `repeat(auto-fit, minmax(${normalizeCssValue(layout.minimumColumnWidth)}, 1fr))`;
    }
  } else if (layout.type === "flex" && layout.orientation !== "vertical") {
    classes.push("pressbridge-layout-row");
    Object.assign(style, getFlexLayoutStyle(layout, block));
  } else if (layout.type === "flex" && layout.orientation === "vertical") {
    classes.push("pressbridge-layout-stack", "pressbridge-layout-stack-flex");
    Object.assign(style, getFlexLayoutStyle(layout, block));
  } else {
    classes.push("pressbridge-layout-stack");
  }

  return {
    className: classes.join(" "),
    style
  };
}

function getGroupStyle(block) {
  return getBlockStyle(block);
}

function isTextLikeBlock(block) {
  const name = block?.name || "";

  if (["core/heading", "core/paragraph", "core/list", "core/quote", "core/buttons", "core/button", "core/spacer"].includes(name)) {
    return true;
  }

  if ("core/group" === name) {
    return (block?.inner_blocks || []).every(isTextLikeBlock);
  }

  return false;
}

function hasCardLikePresentation(block) {
  const className = block?.attrs?.className || "";
  const style = block?.attrs?.style || {};

  return Boolean(
    className.includes("is-style-") ||
      style?.dimensions?.minHeight ||
      style?.color?.background ||
      style?.spacing?.padding
  );
}

function isMediaCardGroup(block) {
  if ("core/group" !== block?.name) {
    return false;
  }

  const children = block?.inner_blocks || [];

  if (children.length < 2 || children.length > 3) {
    return false;
  }

  const first = children[0];
  const second = children[1];

  return (
    ["core/cover", "core/image"].includes(first?.name) &&
    ["core/paragraph", "core/heading"].includes(second?.name)
  );
}

function getGroupLayoutParts(block) {
  const blocks = block?.inner_blocks || [];
  const layout = block?.attrs?.layout || {};
  const firstBlock = blocks[0];

  if (
    "grid" === layout.type &&
    blocks.length >= 3 &&
    firstBlock &&
    isTextLikeBlock(firstBlock) &&
    !hasCardLikePresentation(firstBlock) &&
    blocks.slice(1).some((item) => !isTextLikeBlock(item))
  ) {
    return {
      introBlock: firstBlock,
      layoutBlock: {
        ...block,
        inner_blocks: blocks.slice(1)
      }
    };
  }

  return {
    introBlock: null,
    layoutBlock: block
  };
}

function getCoverStyle(block) {
  const url = block?.attrs?.url;
  const minHeight = normalizeCssValue(block?.attrs?.minHeight);
  const style = mergeStyles(getBlockStyle(block)) || {};

  if (url) {
    style.backgroundImage = `url("${url}")`;
  }

  if (minHeight) {
    style.minHeight = minHeight;
  }

  return style;
}

function getCoverOverlayStyle(block) {
  const dimRatio = Number(block?.attrs?.dimRatio ?? 50);
  const overlayColor = block?.attrs?.overlayColor || block?.attrs?.customOverlayColor || "#17324d";

  return {
    backgroundColor: overlayColor,
    opacity: Math.max(0, Math.min(dimRatio, 100)) / 100
  };
}

function getImageData(block) {
  const attrs = block?.attrs || {};
  const parsed = parseBlockHtml(block);
  const image = parsed?.querySelector("img");
  const caption = parsed?.querySelector("figcaption");
  const inlineStyle = getElementStyleMap(image);

  return {
    alt: attrs.alt || image?.getAttribute("alt") || "",
    caption: attrs.caption || caption?.innerHTML || "",
    height: attrs.height || image?.getAttribute("height") || inlineStyle.height || undefined,
    inlineStyle,
    url: attrs.url || image?.getAttribute("src") || "",
    width: attrs.width || image?.getAttribute("width") || inlineStyle.width || undefined
  };
}

function getImageStyle(block) {
  const image = getImageData(block);
  const attrs = block?.attrs || {};
  const style = {};
  const aspectRatio = normalizeCssValue(attrs.aspectRatio || image.inlineStyle?.["aspect-ratio"]);
  const objectFit = attrs.scale || image.inlineStyle?.["object-fit"];
  const width = normalizeCssValue(image.width);
  const height = normalizeCssValue(image.height);

  if (aspectRatio) {
    style.aspectRatio = aspectRatio;
  }

  if (objectFit) {
    style.objectFit = objectFit;
  }

  if (width) {
    style.width = width;
  }

  if (height) {
    style.height = height;
  }

  return Object.keys(style).length ? style : undefined;
}

function getImageWrapperStyle(block) {
  return getBlockStyle(block);
}

function getMediaTextData(block) {
  const attrs = block?.attrs || {};
  const parsed = parseBlockHtml(block);
  const mediaImage = parsed?.querySelector(".wp-block-media-text__media img");
  const mediaVideo = parsed?.querySelector(".wp-block-media-text__media video");
  const mediaLink = parsed?.querySelector(".wp-block-media-text__media a");

  return {
    alt: mediaImage?.getAttribute("alt") || "",
    mediaPosition:
      attrs.mediaPosition ||
      (parsed?.querySelector(".has-media-on-the-right") || parsed?.firstElementChild?.classList?.contains("has-media-on-the-right")
        ? "right"
        : "left"),
    mediaWidth: Number(attrs.mediaWidth || 50),
    mediaUrl:
      mediaImage?.getAttribute("src") ||
      mediaVideo?.getAttribute("src") ||
      mediaLink?.getAttribute("href") ||
      "",
    mediaType: mediaVideo ? "video" : "image",
    stackedOnMobile: Boolean(attrs.isStackedOnMobile)
  };
}

function getButtonData(block) {
  const attrs = block?.attrs || {};
  const parsed = parseBlockHtml(block);
  const anchor = parsed?.querySelector("a");

  return {
    target: attrs.linkTarget || anchor?.getAttribute("target") || "",
    text: anchor ? (anchor.textContent || "").trim() : getBlockText(block),
    url: attrs.url || anchor?.getAttribute("href") || ""
  };
}

function BlockHtmlFallback({ block, className = "" }) {
  const html = getBlockHtml(block);

  if (!html) {
    return null;
  }

  return (
    <div
      className={blockClasses(block, "pressbridge-html-block", className)}
      dangerouslySetInnerHTML={{ __html: html }}
    />
  );
}

function BlockRenderer({ block }) {
  if (!block?.name) {
    return null;
  }

  switch (block.name) {
    case "core/paragraph":
      return (
        <div
          className={blockClasses(block, "pressbridge-rich-block")}
          dangerouslySetInnerHTML={{ __html: getBlockHtml(block) }}
        />
      );

    case "core/heading": {
      return <BlockHtmlFallback block={block} className="pressbridge-heading-block" />;
    }

    case "core/image": {
      const image = getImageData(block);

      if (!image.url) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <figure className={blockClasses(block, "pressbridge-image-block")} style={getImageWrapperStyle(block)}>
          <img src={image.url} alt={image.alt} style={getImageStyle(block)} />
          {image.caption ? <figcaption dangerouslySetInnerHTML={{ __html: image.caption }} /> : null}
        </figure>
      );
    }

    case "core/gallery":
      if (!block?.inner_blocks?.length) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <div
          className={blockClasses(block, "pressbridge-gallery")}
          style={{
            "--pressbridge-grid-columns": String(
              Math.max(1, Number(block?.attrs?.columns || block.inner_blocks.length || 1))
            ),
            ...(getGalleryStyle(block) || {})
          }}
        >
          <BlockList blocks={block.inner_blocks} />
        </div>
      );

    case "core/media-text": {
      const media = getMediaTextData(block);

      if (!media.mediaUrl) {
        return <BlockHtmlFallback block={block} />;
      }

      const classNames = [
        blockClasses(block, "pressbridge-media-text"),
        media.mediaPosition === "right" ? "pressbridge-media-text--right" : "",
        media.stackedOnMobile ? "pressbridge-media-text--stack-mobile" : ""
      ]
        .filter(Boolean)
        .join(" ");

      return (
        <section
          className={classNames}
          style={{
            ...(getBlockStyle(block) || {}),
            "--pressbridge-media-width": `${Math.max(20, Math.min(media.mediaWidth || 50, 80))}%`
          }}
        >
          <div className="pressbridge-media-text__media">
            {media.mediaType === "video" ? (
              <video controls src={media.mediaUrl} />
            ) : (
              <img src={media.mediaUrl} alt={media.alt} />
            )}
          </div>
          <div className="pressbridge-media-text__content">
            {block?.inner_blocks?.length ? <BlockList blocks={block.inner_blocks} /> : <BlockHtmlFallback block={block} />}
          </div>
        </section>
      );
    }

    case "core/group": {
      if (!block?.inner_blocks?.length) {
        return <BlockHtmlFallback block={block} />;
      }

      const { introBlock, layoutBlock } = getGroupLayoutParts(block);
      const innerLayout = getGroupInnerLayout(layoutBlock);
      const isMediaCard = isMediaCardGroup(block);

      return (
        <section
          className={blockClasses(
            block,
            "pressbridge-group",
            introBlock ? "pressbridge-group--section" : "",
            isMediaCard ? "pressbridge-group--media-card" : ""
          )}
          style={getGroupStyle(block)}
        >
          {introBlock ? <div className="pressbridge-group__intro"><BlockList blocks={[introBlock]} /></div> : null}
          <div className={innerLayout.className} style={innerLayout.style}>
            <BlockList blocks={layoutBlock.inner_blocks} />
          </div>
        </section>
      );
    }

    case "core/columns":
      if (!block?.inner_blocks?.length) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <div
          className={blockClasses(block, "pressbridge-columns")}
          style={getColumnsStyle(block)}
        >
          <BlockList blocks={block.inner_blocks} />
        </div>
      );

    case "core/column":
      if (!block?.inner_blocks?.length) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <div className={blockClasses(block, "pressbridge-column")} style={getColumnStyle(block)}>
          <BlockList blocks={block.inner_blocks} />
        </div>
      );

    case "core/buttons":
      if (!block?.inner_blocks?.length) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <div className={blockClasses(block, "pressbridge-buttons")}>
          <BlockList blocks={block.inner_blocks} />
        </div>
      );

    case "core/button": {
      const { target, text, url } = getButtonData(block);

      if (!url || !text) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <AppLink className={blockClasses(block, "pressbridge-button__link")} href={url} target={target}>
          {text}
        </AppLink>
      );
    }

    case "core/list":
    case "core/quote":
      return <BlockHtmlFallback block={block} />;

    case "core/cover": {
      const style = getCoverStyle(block);

      if (!block?.inner_blocks?.length && !style.backgroundImage) {
        return <BlockHtmlFallback block={block} />;
      }

      return (
        <section className={blockClasses(block, "pressbridge-cover")} style={style}>
          <span className="pressbridge-cover__overlay" style={getCoverOverlayStyle(block)} aria-hidden="true" />
          <div className="pressbridge-cover__content">
            {block?.inner_blocks?.length ? <BlockList blocks={block.inner_blocks} /> : null}
          </div>
        </section>
      );
    }

    case "core/spacer":
      return (
        <div
          className={blockClasses(block, "pressbridge-spacer")}
          style={{ height: normalizeCssValue(block?.attrs?.height) || "1.5rem" }}
          aria-hidden="true"
        />
      );

    case "core/separator":
      return <hr className={blockClasses(block, "pressbridge-separator")} style={getSeparatorStyle(block)} />;

    default:
      return <BlockHtmlFallback block={block} className="pressbridge-fallback-block" />;
  }
}

function BlockList({ blocks = [] }) {
  if (!blocks.length) {
    return null;
  }

  return (
    <>
      {blocks.map((block, index) => (
        <BlockRenderer
          key={`${block.name || "unknown"}-${block.attrs?.id || block.attrs?.url || index}`}
          block={block}
        />
      ))}
    </>
  );
}

function BlockContent({ blocks = [], html = "" }) {
  if (blocks.length) {
    return (
      <div className="content-body content-body-blocks">
        <BlockList blocks={blocks} />
      </div>
    );
  }

  if (!html) {
    return null;
  }

  return (
    <div className="content-body content-body-html" dangerouslySetInnerHTML={{ __html: html }} />
  );
}

function formatPreviewTime(value) {
  if (!value) {
    return "";
  }

  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short"
    }).format(new Date(value));
  } catch (error) {
    return value;
  }
}

function PreviewBanner({ content, currentPath }) {
  if (!content?.is_preview) {
    return null;
  }

  const preview = content.preview || {};
  const publishedPath = preview.canonical_path || content.path || "/";
  const previewModePath =
    currentPath === publishedPath ? `${publishedPath}?wtr_preview_token=...` : currentPath;

  return (
    <section className="content-card preview-card">
      <p className="eyebrow">Preview mode</p>
      <h2>Signed WordPress preview</h2>
      <p className="lede">
        {preview.source_label ||
          "You are looking at a temporary preview being served directly from WordPress."}
      </p>
      <p className="meta-row">
        Preview URL: <strong>{previewModePath}</strong>
      </p>
      {preview.token_expires_at ? (
        <p className="meta-row">
          Link expires: <strong>{formatPreviewTime(preview.token_expires_at)}</strong>
        </p>
      ) : null}
      <div className="action-row">
        <AppLink className="button-link" href={publishedPath}>
          Open published route
        </AppLink>
      </div>
    </section>
  );
}

function ContentView({ content }) {
  const isDocumentPage = content?.post_type === "page" && Array.isArray(content?.blocks) && content.blocks.length > 0;
  const className = ["content-card", isDocumentPage ? "content-card--document" : ""].filter(Boolean).join(" ");

  return (
    <article className={className}>
      <p className="eyebrow">{content.post_type_label || content.post_type}</p>
      <h1>{content.title}</h1>
      {content.featured_image?.url ? (
        <img
          className="hero-image"
          src={content.featured_image.url}
          alt={content.featured_image.alt || content.title}
        />
      ) : null}
      <BlockContent blocks={content.blocks || []} html={content.content} />
    </article>
  );
}

function ArchiveView({ route }) {
  const items = route.items || [];

  return (
    <section className="content-card">
      <p className="eyebrow">{route.post_type_label || route.post_type} archive</p>
      <h2>{route.title}</h2>
      {route.description ? <p className="lede">{route.description}</p> : null}
      <p className="meta-row">
        {route.totalItems} item{route.totalItems === 1 ? "" : "s"}
        {route.search ? ` matching "${route.search}"` : ""}
      </p>

      {items.length ? (
        <div className="archive-grid">
          {items.map((item) => (
            <article key={item.id} className="archive-card">
              <p className="eyebrow">{item.post_type_label || item.post_type}</p>
              <h3>
                <AppLink href={item.path}>{item.title}</AppLink>
              </h3>
              {item.excerpt ? <p>{item.excerpt}</p> : null}
            </article>
          ))}
        </div>
      ) : (
        <div className="empty-state">
          <h3>No published content yet</h3>
          <p>This archive route is connected, but WordPress does not have any published items here yet.</p>
        </div>
      )}
    </section>
  );
}

function FailureState({ message }) {
  return (
    <section className="content-card">
      <p className="eyebrow">Connection issue</p>
      <h2>The React frontend could not complete the bridge request</h2>
      <p className="lede">{message}</p>
      <ul className="stack-list">
        <li>Confirm WordPress is running and reachable from this frontend.</li>
        <li>Confirm the PressBridge plugin is active and the custom REST routes are available.</li>
        <li>Confirm the frontend API base matches the WordPress site you want to render.</li>
      </ul>
    </section>
  );
}

function HomeExperience({ site, route, pages, posts }) {
  return (
    <>
      <HeroSection site={site} pages={pages} posts={posts} />
      <SystemOverview site={site} />
      <BenefitGrid />
      <LiveProof site={site} route={route} />
      <RouteExamples pages={pages} posts={posts} route={route} />
      <LatestContentSection route={route} />
      <SystemHealth site={site} />
    </>
  );
}

function EmptyRoute() {
  return (
    <section className="content-card">
      <p className="eyebrow">Route not found</p>
      <h1>This path is not published in WordPress yet</h1>
      <p>Publish content for this route, or update the frontend to point at a path that WordPress can resolve.</p>
    </section>
  );
}

function App() {
  const [route, setRoute] = useState(() => window.location.pathname + window.location.search);
  const [site, setSite] = useState(null);
  const [menus, setMenus] = useState(null);
  const [pages, setPages] = useState([]);
  const [posts, setPosts] = useState([]);
  const [routeData, setRouteData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const routeState = useMemo(() => {
    const url = new URL(route, window.location.origin);
    return {
      pathname: url.pathname,
      search: url.search,
      previewToken: url.searchParams.get("wtr_preview_token")
    };
  }, [route]);

  useEffect(() => {
    const handlePopState = () => {
      setRoute(window.location.pathname + window.location.search);
    };

    window.addEventListener("popstate", handlePopState);
    return () => window.removeEventListener("popstate", handlePopState);
  }, []);

  useEffect(() => {
    async function bootstrap() {
      const [siteData, menuData, pageData, postData] = await Promise.all([
        apiFetch("/site"),
        apiFetch("/menus"),
        apiFetch("/pages?per_page=10"),
        apiFetch("/posts?per_page=10")
      ]);

      setSite(siteData);
      setMenus(menuData);
      setPages(pageData.items || []);
      setPosts(postData.items || []);
    }

    bootstrap().catch((bootstrapError) => {
      setError(
        bootstrapError.message ||
          "Unable to load the WordPress bridge configuration for this frontend."
      );
      setLoading(false);
    });
  }, []);

  useEffect(() => {
    async function loadRoute() {
      if (!site) {
        return;
      }

      setLoading(true);
      setError("");
      setRouteData(null);

      try {
        if (routeState.previewToken) {
          const previewData = await apiFetch(`/preview/${encodeURIComponent(routeState.previewToken)}`);
          setRouteData(previewData);
          return;
        }

        const resolved = await apiFetch(`/resolve?path=${encodeURIComponent(routeState.pathname)}`);
        setRouteData(resolved);
      } catch (routeError) {
        if (routeError?.status === 404) {
          setRouteData(null);
          return;
        }

        setError(routeError.message || "Route lookup failed.");
      } finally {
        setLoading(false);
      }
    }

    loadRoute();
  }, [routeState, site]);

  return (
    <div className="app-shell">
      <Header site={site} menus={menus} pages={pages} posts={posts} />

      <main className="site-main">
        {loading ? <p className="status">Loading content...</p> : null}
        {!loading && error ? <FailureState message={error} /> : null}
        {!loading && !error && routeData?.is_preview ? (
          <PreviewBanner content={routeData} currentPath={route} />
        ) : null}
        {!loading && !error && routeData?.route_type === "singular" ? (
          <ContentView content={routeData} />
        ) : null}
        {!loading && !error && routeData?.route_type === "archive" && routeData?.path === "/" ? (
          <HomeExperience site={site} route={routeData} pages={pages} posts={posts} />
        ) : null}
        {!loading && !error && routeData?.route_type === "archive" && routeData?.path !== "/" ? (
          <ArchiveView route={routeData} />
        ) : null}
        {!loading && !error && !routeData ? <EmptyRoute /> : null}
      </main>

      <Footer site={site} menus={menus} pages={pages} posts={posts} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
