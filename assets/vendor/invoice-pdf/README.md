# Invoice PDF dependencies

Locally hosted, loaded only on demand from the invoice print page. No invoice
contents are submitted to a remote PDF service.

- pdf-lib 1.17.1 (MIT): https://pdf-lib.js.org/docs/api/classes/pdfdocument
- @pdf-lib/fontkit 1.1.1 (MIT): https://github.com/Hopding/fontkit
  The upstream package ships its MIT declaration in README.md/package.json,
  retained here as fontkit-README.md and fontkit-package.json.
- bidi-js 1.0.3 (MIT): https://github.com/lojjic/bidi-js
- PDF.js 6.3.289 (Apache-2.0), already bundled under ../pdfjs/, renders previews.

Final PDFs contain embedded Tanha-FD.ttf text and vector borders/backgrounds.
Only supplied logos and stamps remain bitmap images, at their source resolution.
No full-page screenshot is embedded. html2canvas is a legacy unused dependency.

The accompanying license files must be retained with these distributions.
