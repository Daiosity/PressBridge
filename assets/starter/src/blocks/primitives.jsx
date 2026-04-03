export function Section({ as: Tag = "section", className = "", style, children }) {
  return (
    <Tag className={className} style={style}>
      {children}
    </Tag>
  );
}

export function Container({ as: Tag = "div", className = "", style, children }) {
  return (
    <Tag className={className} style={style}>
      {children}
    </Tag>
  );
}

export function Grid({ className = "", columns = 2, style, children }) {
  return (
    <div
      className={className}
      style={{ "--pressbridge-grid-columns": String(columns), ...(style || {}) }}
    >
      {children}
    </div>
  );
}

export function Stack({ className = "", children }) {
  return <div className={className}>{children}</div>;
}

export function RichText({ as: Tag = "div", className = "", html = "" }) {
  return <Tag className={className} dangerouslySetInnerHTML={{ __html: html }} />;
}

export function Media({ className = "", src, alt = "", caption = "", style, imageStyle }) {
  return (
    <figure className={className} style={style}>
      <img src={src} alt={alt} style={imageStyle} />
      {caption ? <figcaption dangerouslySetInnerHTML={{ __html: caption }} /> : null}
    </figure>
  );
}

export function ButtonGroup({ className = "", children }) {
  return <div className={className}>{children}</div>;
}
