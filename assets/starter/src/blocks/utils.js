export function blockClasses(block, ...extraClasses) {
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

export function getBlockHtml(block) {
  return block?.rendered_html || block?.inner_html || "";
}

export function getBlockText(block) {
  const html = getBlockHtml(block);

  if (!html) {
    return "";
  }

  if (typeof window !== "undefined" && window.document) {
    const element = window.document.createElement("div");
    element.innerHTML = html;
    return (element.textContent || element.innerText || "").trim();
  }

  return html.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
}

function parseBlockHtml(block) {
  const html = getBlockHtml(block);

  if (!html || typeof window === "undefined" || !window.document) {
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

export function normalizeCssValue(value) {
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

export function getSpacingStyle(spacing = {}) {
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

export function getDimensionStyle(dimensions = {}) {
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

export function getColorStyle(color = {}) {
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

export function getTypographyStyle(block) {
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

export function mergeStyles(...styles) {
  const merged = Object.assign({}, ...styles.filter(Boolean));

  return Object.keys(merged).length ? merged : undefined;
}

export function getBlockStyle(block) {
  return mergeStyles(
    getSpacingStyle(block?.attrs?.style?.spacing),
    getDimensionStyle(block?.attrs?.style?.dimensions),
    getColorStyle(block?.attrs?.style?.color),
    getTypographyStyle(block)
  );
}

export function getGalleryStyle(block) {
  const style = mergeStyles(getSpacingStyle(block?.attrs?.style?.spacing)) || {};
  const columns = Number(block?.attrs?.columns || 0);

  if (columns > 0) {
    style.gridTemplateColumns = `repeat(${columns}, minmax(0, 1fr))`;
  }

  return Object.keys(style).length ? style : undefined;
}

export function getSeparatorStyle(block) {
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

export function getFlexLayoutStyle(layout = {}, block = null) {
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

export function getColumnStyle(block) {
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

export function getCoverStyle(block) {
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

export function getCoverOverlayStyle(block) {
  const dimRatio = Number(block?.attrs?.dimRatio ?? 50);
  const overlayColor = block?.attrs?.overlayColor || block?.attrs?.customOverlayColor || "#17324d";

  return {
    backgroundColor: overlayColor,
    opacity: Math.max(0, Math.min(dimRatio, 100)) / 100
  };
}

export function getImageData(block) {
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

export function getImageStyle(block) {
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

export function getImageWrapperStyle(block) {
  return getBlockStyle(block);
}

export function getMediaTextData(block) {
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

export function getButtonData(block) {
  const attrs = block?.attrs || {};
  const parsed = parseBlockHtml(block);
  const anchor = parsed?.querySelector("a");

  return {
    target: attrs.linkTarget || anchor?.getAttribute("target") || "",
    text: anchor ? (anchor.textContent || "").trim() : getBlockText(block),
    url: attrs.url || anchor?.getAttribute("href") || ""
  };
}
