from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path


ROOT = Path(__file__).resolve().parent
DIST_ROOT = ROOT.parent / "frontend-app" / "dist"
SERVE_ROOT = DIST_ROOT if (DIST_ROOT / "index.html").exists() else ROOT


class SpaHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(SERVE_ROOT), **kwargs)

    def do_GET(self):
        requested = SERVE_ROOT / self.path.lstrip("/")

        if self.path == "/" or requested.exists():
            return super().do_GET()

        self.path = "/index.html"
        return super().do_GET()


if __name__ == "__main__":
    server = ThreadingHTTPServer(("127.0.0.1", 5173), SpaHandler)
    source = "frontend-app/dist" if SERVE_ROOT == DIST_ROOT else "frontend-lite"
    print(f"Serving PressBridge frontend from {source} at http://127.0.0.1:5173")
    server.serve_forever()
