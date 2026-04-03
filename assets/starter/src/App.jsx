import { useEffect, useState } from "react";
import { Link, useLocation } from "react-router-dom";
import {
  fetchMenus,
  fetchPages,
  fetchPosts,
  fetchPreviewContent,
  fetchSiteConfig,
  resolveContent
} from "./lib/api";
import { BlockContent } from "./blocks/BlockRenderer";

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

  if (link.internal) {
    return (
      <Link className={className} to={link.href}>
        {item.title}
      </Link>
    );
  }

  return (
    <a
      className={className}
      href={link.href}
      target={link.target || "_self"}
      rel={link.target === "_blank" ? "noreferrer" : undefined}
    >
      {item.title}
    </a>
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
          <Link className="nav-link" to={item.href}>
            {item.title}
          </Link>
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
          <Link className="footer-link" to={item.href}>
            {item.title}
          </Link>
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
        <Link className="site-title" to="/">
          PressBridge
        </Link>
        <p className="site-description">Connect WordPress to modern frontends.</p>
      </div>

      <div className="header-nav-block">
        <Navigation menus={menus} siteHomeUrl={site?.home_url} pages={pages} posts={posts} />
        {ctaPage ? (
          <Link className="button-link button-link-primary header-cta" to={ctaPage.path}>
            {ctaPage.slug === "contact" ? "Contact" : `Explore ${ctaPage.title}`}
          </Link>
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
            <Link className="button-link button-link-primary" to={primaryPage.path}>
              Open sample page
            </Link>
          ) : null}
          {primaryPost ? (
            <Link className="button-link" to={primaryPost.path}>
              Read latest post
            </Link>
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
              <Link to={samplePage.path}>{samplePage.title}</Link>
            </h3>
            <p>
              {samplePage.excerpt ||
                "This published WordPress page is being resolved and rendered through the frontend."}
            </p>
            <div className="action-row">
              <Link className="button-link" to={samplePage.path}>
                Open page
              </Link>
            </div>
          </article>
        ) : null}
        {latestPost ? (
          <article key={latestPost.id} className="archive-card">
            <p className="eyebrow">Latest post</p>
            <h3>
              <Link to={latestPost.path}>{latestPost.title}</Link>
            </h3>
            <p>{latestPost.excerpt || "The latest published post is already available through the React frontend."}</p>
            <div className="action-row">
              <Link className="button-link" to={latestPost.path}>
                Read post
              </Link>
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
              <Link to={item.path}>{item.title}</Link>
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
        <Link className="button-link" to={publishedPath}>
          Open published route
        </Link>
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
                <Link to={item.path}>{item.title}</Link>
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

export default function App() {
  const location = useLocation();
  const currentPath = `${location.pathname}${location.search}`;
  const [site, setSite] = useState(null);
  const [menus, setMenus] = useState(null);
  const [pages, setPages] = useState([]);
  const [routeData, setRouteData] = useState(null);
  const [posts, setPosts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    async function bootstrap() {
      const [siteData, menuData, pageData, postData] = await Promise.all([
        fetchSiteConfig(),
        fetchMenus(),
        fetchPages(),
        fetchPosts()
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
      setLoading(true);
      setError("");
      setRouteData(null);

      try {
        const params = new URLSearchParams(location.search);
        const previewToken = params.get("wtr_preview_token");

        if (previewToken) {
          const previewData = await fetchPreviewContent(previewToken);
          setRouteData(previewData);
          return;
        }

        const resolved = await resolveContent(location.pathname);
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

    if (site) {
      loadRoute();
    }
  }, [location.pathname, location.search, site]);

  return (
    <div className="app-shell">
      <Header site={site} menus={menus} pages={pages} posts={posts} />

      <main className="site-main">
        {loading ? <p className="status">Loading content...</p> : null}
        {!loading && error ? <FailureState message={error} /> : null}
        {!loading && !error && routeData?.is_preview ? (
          <PreviewBanner content={routeData} currentPath={currentPath} />
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
