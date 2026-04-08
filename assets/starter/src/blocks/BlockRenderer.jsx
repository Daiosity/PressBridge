import { BLOCK_RENDERERS, FallbackBlockRenderer } from "./renderers";

export function BlockRenderer({ block }) {
  if (!block?.name) {
    return null;
  }

  const Renderer = BLOCK_RENDERERS[block.name] || FallbackBlockRenderer;

  return <Renderer block={block} renderBlocks={(blocks) => <BlockList blocks={blocks} />} />;
}

export function BlockList({ blocks = [] }) {
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

export function BlockContent({ blocks = [], html = "", renderMode = "blocks", compatibility = {} }) {
  const preferHtml = renderMode === "html" && Boolean(html);

  if (blocks.length && !preferHtml) {
    return (
      <div className="content-body content-body-blocks">
        <BlockList blocks={blocks} />
      </div>
    );
  }

  if (!html) {
    return null;
  }

  const htmlClassName = [
    "content-body",
    "content-body-html",
    compatibility?.is_shortcode_content ? "content-body-html--shortcode" : "",
    compatibility?.is_woocommerce_shortcode_page ? "content-body-html--woocommerce" : ""
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <div className={htmlClassName} dangerouslySetInnerHTML={{ __html: html }} />
  );
}
