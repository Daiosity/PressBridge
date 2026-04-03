import { ButtonGroup, Container, Grid, Media, RichText, Section } from "./primitives";
import {
  blockClasses,
  getBlockStyle,
  getButtonData,
  getBlockHtml,
  getColumnStyle,
  getCoverOverlayStyle,
  getCoverStyle,
  getFlexLayoutStyle,
  getGalleryStyle,
  getImageData,
  getImageStyle,
  getImageWrapperStyle,
  getMediaTextData,
  getSeparatorStyle,
  getSpacingStyle,
  mergeStyles,
  normalizeCssValue
} from "./utils";

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

function HtmlRenderer({ block, className = "" }) {
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

function ParagraphRenderer({ block }) {
  return <RichText as="div" className={blockClasses(block, "pressbridge-rich-block")} html={getBlockHtml(block)} />;
}

function HeadingRenderer({ block }) {
  return <HtmlRenderer block={block} className="pressbridge-heading-block" />;
}

function ImageRenderer({ block }) {
  const image = getImageData(block);

  if (!image.url) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <Media
      className={blockClasses(block, "pressbridge-image-block")}
      src={image.url}
      alt={image.alt}
      caption={image.caption}
      style={getImageWrapperStyle(block)}
      imageStyle={getImageStyle(block)}
    />
  );
}

function GalleryRenderer({ block, renderBlocks }) {
  if (!block?.inner_blocks?.length) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <Grid
      className={blockClasses(block, "pressbridge-gallery")}
      columns={Math.max(1, Number(block?.attrs?.columns || block.inner_blocks.length || 1))}
      style={getGalleryStyle(block)}
    >
      {renderBlocks(block.inner_blocks)}
    </Grid>
  );
}

function MediaTextRenderer({ block, renderBlocks }) {
  const media = getMediaTextData(block);

  if (!media.mediaUrl) {
    return <HtmlRenderer block={block} />;
  }

  const classNames = [
    blockClasses(block, "pressbridge-media-text"),
    media.mediaPosition === "right" ? "pressbridge-media-text--right" : "",
    media.stackedOnMobile ? "pressbridge-media-text--stack-mobile" : ""
  ]
    .filter(Boolean)
    .join(" ");

  const style = mergeStyles(getBlockStyle(block), {
    "--pressbridge-media-width": `${Math.max(20, Math.min(media.mediaWidth || 50, 80))}%`
  });

  return (
    <Section className={classNames} style={style}>
      <Container className="pressbridge-media-text__media">
        {media.mediaType === "video" ? (
          <video controls src={media.mediaUrl} />
        ) : (
          <img src={media.mediaUrl} alt={media.alt} />
        )}
      </Container>
      <Container className="pressbridge-media-text__content">
        {block?.inner_blocks?.length ? renderBlocks(block.inner_blocks) : <HtmlRenderer block={block} />}
      </Container>
    </Section>
  );
}

function GroupRenderer({ block, renderBlocks }) {
  if (!block?.inner_blocks?.length) {
    return <HtmlRenderer block={block} />;
  }

  const { introBlock, layoutBlock } = getGroupLayoutParts(block);
  const innerLayout = getGroupInnerLayout(layoutBlock);
  const isMediaCard = isMediaCardGroup(block);

  return (
    <Section
      className={blockClasses(
        block,
        "pressbridge-group",
        introBlock ? "pressbridge-group--section" : "",
        isMediaCard ? "pressbridge-group--media-card" : ""
      )}
      style={getGroupStyle(block)}
    >
      {introBlock ? <Container className="pressbridge-group__intro">{renderBlocks([introBlock])}</Container> : null}
      <Container className={innerLayout.className} style={innerLayout.style}>
        {renderBlocks(layoutBlock.inner_blocks)}
      </Container>
    </Section>
  );
}

function ColumnsRenderer({ block, renderBlocks }) {
  if (!block?.inner_blocks?.length) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <Grid
      className={blockClasses(block, "pressbridge-columns")}
      columns={Math.max(1, block.inner_blocks.length)}
      style={getColumnsStyle(block)}
    >
      {renderBlocks(block.inner_blocks)}
    </Grid>
  );
}

function ColumnRenderer({ block, renderBlocks }) {
  if (!block?.inner_blocks?.length) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <Container className={blockClasses(block, "pressbridge-column")} style={getColumnStyle(block)}>
      {renderBlocks(block.inner_blocks)}
    </Container>
  );
}

function ButtonsRenderer({ block, renderBlocks }) {
  if (!block?.inner_blocks?.length) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <ButtonGroup className={blockClasses(block, "pressbridge-buttons")}>
      {renderBlocks(block.inner_blocks)}
    </ButtonGroup>
  );
}

function ButtonRenderer({ block }) {
  const { target, text, url } = getButtonData(block);

  if (!url || !text) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <a
      className={blockClasses(block, "pressbridge-button__link")}
      href={url}
      target={target || undefined}
      rel={target === "_blank" ? "noreferrer" : undefined}
    >
      {text}
    </a>
  );
}

function ListRenderer({ block }) {
  return <HtmlRenderer block={block} />;
}

function QuoteRenderer({ block }) {
  return <HtmlRenderer block={block} />;
}

function CoverRenderer({ block, renderBlocks }) {
  const style = getCoverStyle(block);

  if (!block?.inner_blocks?.length && !style.backgroundImage) {
    return <HtmlRenderer block={block} />;
  }

  return (
    <Section className={blockClasses(block, "pressbridge-cover")} style={style}>
      <span className="pressbridge-cover__overlay" style={getCoverOverlayStyle(block)} aria-hidden="true" />
      <Container className="pressbridge-cover__content">
        {block?.inner_blocks?.length ? renderBlocks(block.inner_blocks) : null}
      </Container>
    </Section>
  );
}

function SpacerRenderer({ block }) {
  const height = normalizeCssValue(block?.attrs?.height);

  return (
    <div
      className={blockClasses(block, "pressbridge-spacer")}
      style={{ height: height || "1.5rem" }}
      aria-hidden="true"
    />
  );
}

function SeparatorRenderer({ block }) {
  return <hr className={blockClasses(block, "pressbridge-separator")} style={getSeparatorStyle(block)} />;
}

export const BLOCK_RENDERERS = {
  "core/button": ButtonRenderer,
  "core/buttons": ButtonsRenderer,
  "core/column": ColumnRenderer,
  "core/columns": ColumnsRenderer,
  "core/cover": CoverRenderer,
  "core/gallery": GalleryRenderer,
  "core/group": GroupRenderer,
  "core/heading": HeadingRenderer,
  "core/image": ImageRenderer,
  "core/list": ListRenderer,
  "core/media-text": MediaTextRenderer,
  "core/paragraph": ParagraphRenderer,
  "core/quote": QuoteRenderer,
  "core/separator": SeparatorRenderer,
  "core/spacer": SpacerRenderer
};

export function FallbackBlockRenderer({ block }) {
  return <HtmlRenderer block={block} className="pressbridge-fallback-block" />;
}
