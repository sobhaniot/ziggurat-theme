(function () {
  'use strict';

  var form = document.querySelector('[data-letter-calculator]');
  var ratesForm = document.querySelector('[data-letter-rates-form]');
  if (!form || !ratesForm) return;

  var digitMap = {'۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9','٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9'};
  var cachedFile = null;
  var cachedText = '';
  var droppedFile = null;
  var sourceRatio = 0;
  var analysisState = null;
  var currentCalculation = null;
  var saveRatesTimer = null;
  var saveValuesTimer = null;
  var progressTimer = null;
  var progressStartedAt = 0;
  var progressMessage = '';
  var filePreviewUrl = '';
  var estimateSearchTimer = null;
  var edgeLabels = {swedish: 'لبه سوئدی', plastic: 'لبه پلاستیک', channelium: 'لبه چلنیوم', metal: 'لبه فلزی'};
  var smdLabels = {block: 'SMD بلوکی', lens: 'SMD لنزدار', roll: 'SMD رولوکی'};
  var transformerWatts = [400, 300, 200, 120, 100, 60];

  function normalize(value) {
    return String(value || '').replace(/[۰-۹٠-٩]/g, function (digit) { return digitMap[digit] || digit; });
  }
  function decimal(value) {
    var raw = normalize(value).replace(/\s/g, '').replace(/٬/g, '').replace(/٫/g, '.').replace(/،/g, ',');
    if (raw.indexOf('.') !== -1) {
      raw = raw.replace(/,/g, '');
    } else {
      var parts = raw.split(',');
      var commaIsDecimal = parts.length === 2 && parts[1].length > 0 && (parts[0] === '0' || parts[1].length < 3);
      raw = commaIsDecimal ? parts[0] + '.' + parts[1] : raw.replace(/,/g, '');
    }
    raw = raw.replace(/[^0-9.]/g, '');
    var firstDecimal = raw.indexOf('.');
    if (firstDecimal !== -1) raw = raw.slice(0, firstDecimal + 1) + raw.slice(firstDecimal + 1).replace(/\./g, '');
    return Math.max(0, parseFloat(raw) || 0);
  }
  function money(value) {
    return Math.max(0, parseInt(normalize(value).replace(/[^0-9]/g, ''), 10) || 0);
  }
  function formatMoney(value) {
    return Math.round(value).toLocaleString('fa-IR') + ' ریال';
  }
  function formatMeasure(value, digits) {
    return Number(value.toFixed(digits === undefined ? 2 : digits)).toLocaleString('fa-IR', {maximumFractionDigits: digits === undefined ? 2 : digits});
  }
  function roundUpToOneDecimal(value) {
    return Math.ceil((Number(value) * 10) - 1e-9) / 10;
  }
  function field(name) {
    return form.elements.namedItem(name);
  }
  function rateField(name) {
    return ratesForm.elements.namedItem(name);
  }
  function setText(selector, value) {
    var node = form.querySelector(selector);
    if (node) node.textContent = value;
  }
  function showError(message) {
    var error = form.querySelector('[data-letter-error]');
    error.textContent = message;
    error.hidden = false;
  }
  function clearError() {
    form.querySelector('[data-letter-error]').hidden = true;
  }
  function renderProgressMessage() {
    var progressText = form.querySelector('[data-letter-progress] strong');
    if (!progressText) return;
    var seconds = progressStartedAt ? Math.max(0, Math.round((Date.now() - progressStartedAt) / 1000)) : 0;
    progressText.textContent = progressMessage + (seconds > 0 ? ' — ' + seconds.toLocaleString('fa-IR') + ' ثانیه' : '');
  }
  function updateProgress(message) {
    progressMessage = message;
    renderProgressMessage();
  }
  function setBusy(isBusy) {
    var button = form.querySelector('[data-letter-calculate]');
    var progress = form.querySelector('[data-letter-progress]');
    button.disabled = isBusy;
    button.textContent = isBusy ? 'در حال چیدمان…' : 'تحلیل فایل و چیدمان ورق';
    progress.hidden = !isBusy;
    if (isBusy) {
      progress.removeAttribute('hidden');
    } else {
      progress.setAttribute('hidden', 'hidden');
    }
    window.clearInterval(progressTimer);
    if (isBusy) {
      progressStartedAt = Date.now();
      updateProgress('مرحله ۱ از ۴: خواندن فایل SVG');
      progressTimer = window.setInterval(renderProgressMessage, 1000);
    } else {
      progressStartedAt = 0;
      progressMessage = '';
    }
  }
  function yieldToBrowser() {
    return new Promise(function (resolve) {
      window.requestAnimationFrame(function () { window.setTimeout(resolve, 0); });
    });
  }

  function parseSvgLength(value) {
    var match = String(value || '').trim().match(/^([0-9]*\.?[0-9]+)\s*(mm|cm|in|pt|pc|px)?$/i);
    if (!match) return 0;
    var number = parseFloat(match[1]);
    var unit = (match[2] || 'px').toLowerCase();
    if (unit === 'mm') return number;
    if (unit === 'cm') return number * 10;
    if (unit === 'in') return number * 25.4;
    if (unit === 'pt') return number * 25.4 / 72;
    if (unit === 'pc') return number * 25.4 / 6;
    return number * 25.4 / 96;
  }

  function inlineStyleValue(node, property) {
    var style = String(node && node.getAttribute ? node.getAttribute('style') || '' : '');
    var match = style.match(new RegExp('(?:^|;)\\s*' + property.replace('-', '\\-') + '\\s*:\\s*([^;]+)', 'i'));
    return match ? match[1].trim() : '';
  }

  function svgClassStyles(root) {
    var styles = {};
    root.querySelectorAll('style').forEach(function (styleNode) {
      var css = String(styleNode.textContent || '').replace(/<!\[CDATA\[|\]\]>/g, '');
      var rulePattern = /([^{}]+)\{([^{}]+)\}/g;
      var rule;
      while ((rule = rulePattern.exec(css))) {
        var declarations = {};
        String(rule[2]).split(';').forEach(function (declaration) {
          var separator = declaration.indexOf(':');
          if (separator < 1) return;
          declarations[declaration.slice(0, separator).trim().toLowerCase()] = declaration.slice(separator + 1).trim().replace(/\s*!important\s*$/i, '');
        });
        String(rule[1]).split(',').forEach(function (selector) {
          var classMatch = selector.trim().match(/^\.([a-zA-Z_][\w-]*)$/);
          if (!classMatch) return;
          styles[classMatch[1]] = Object.assign(styles[classMatch[1]] || {}, declarations);
        });
      }
    });
    return styles;
  }

  function classStyleValue(node, property, classStyles) {
    if (!node || !classStyles) return '';
    var value = '';
    String(node.getAttribute('class') || '').trim().split(/\s+/).forEach(function (className) {
      if (classStyles[className] && classStyles[className][property] !== undefined) value = classStyles[className][property];
    });
    return value;
  }

  function inheritedPaint(node, property, fallback, classStyles) {
    var current = node;
    while (current && current.nodeType === 1) {
      var value = inlineStyleValue(current, property)
        || String(current.getAttribute(property) || '').trim()
        || classStyleValue(current, property, classStyles);
      if (value && value.toLowerCase() !== 'inherit') return value;
      current = current.parentNode;
    }
    return fallback;
  }

  function svgColor(value, fallback) {
    var raw = String(value || '').trim().toLowerCase();
    if (!raw || raw === 'none' || raw === 'transparent') return fallback || null;
    var names = {
      black: '#000000', white: '#ffffff', red: '#ff0000', blue: '#0000ff', green: '#008000',
      yellow: '#ffff00', orange: '#ffa500', purple: '#800080', grey: '#808080', gray: '#808080',
      silver: '#c0c0c0', maroon: '#800000', navy: '#000080', teal: '#008080', lime: '#00ff00',
      aqua: '#00ffff', cyan: '#00ffff', magenta: '#ff00ff', fuchsia: '#ff00ff'
    };
    if (names[raw]) return names[raw];
    var shortHex = raw.match(/^#([0-9a-f]{3})$/i);
    if (shortHex) return '#' + shortHex[1].split('').map(function (part) { return part + part; }).join('').toLowerCase();
    var fullHex = raw.match(/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/i);
    if (fullHex) return '#' + fullHex[1].toLowerCase();
    var rgb = raw.match(/^rgba?\(\s*([0-9.]+)(%)?\s*[, ]\s*([0-9.]+)(%)?\s*[, ]\s*([0-9.]+)(%)?/i);
    if (rgb) {
      var channels = [1, 3, 5].map(function (index) {
        var channel = parseFloat(rgb[index]);
        if (rgb[index + 1]) channel = channel * 2.55;
        return Math.max(0, Math.min(255, Math.round(channel)));
      });
      return '#' + channels.map(function (channel) { return channel.toString(16).padStart(2, '0'); }).join('');
    }
    return fallback || null;
  }

  function colorChannels(color) {
    var normalized = svgColor(color, '#000000');
    return [parseInt(normalized.slice(1, 3), 16), parseInt(normalized.slice(3, 5), 16), parseInt(normalized.slice(5, 7), 16)];
  }

  function pathOperation(stroke) {
    var color = svgColor(stroke, null);
    if (!color) return 'primary';
    var channels = colorChannels(color);
    var red = channels[0];
    var green = channels[1];
    var blue = channels[2];
    if (red >= 145 && red >= green * 1.45 && red >= blue * 1.35) return 'pin';
    if (blue >= 120 && blue >= red * 1.35 && blue >= green * 1.2) return 'double';
    return 'primary';
  }

  function plexiColorName(color) {
    var names = {
      '#000000': 'مشکی', '#ffffff': 'سفید', '#ff0000': 'قرمز', '#0000ff': 'آبی',
      '#008000': 'سبز', '#00ff00': 'سبز روشن', '#ffff00': 'زرد', '#ffa500': 'نارنجی',
      '#800080': 'بنفش', '#ff00ff': 'سرخابی', '#00ffff': 'فیروزه‌ای', '#808080': 'خاکستری',
      '#c0c0c0': 'نقره‌ای', '#800000': 'زرشکی', '#000080': 'سرمه‌ای', '#008080': 'سبزآبی'
    };
    return (names[color] ? names[color] + ' ' : '') + color.toUpperCase();
  }

  function geometrySelector() {
    return 'path,polygon,polyline,rect,circle,ellipse,line';
  }

  function assessSvgComplexity(geometry) {
    var pathCount = 0;
    var commandCount = 0;
    var coordinateChars = 0;
    geometry.forEach(function (node) {
      if (String(node.localName || '').toLowerCase() !== 'path') return;
      pathCount += 1;
      var data = String(node.getAttribute('d') || '');
      coordinateChars += data.length;
      commandCount += (data.match(/[a-z]/gi) || []).length;
    });
    var highRisk = pathCount >= 8 || commandCount >= 900 || coordinateChars >= 9000;
    var veryHighRisk = pathCount >= 18 || commandCount >= 2200 || coordinateChars >= 24000;
    return {
      pathCount: pathCount,
      geometryCount: geometry.length,
      commandCount: commandCount,
      coordinateChars: coordinateChars,
      level: veryHighRisk ? 'very-high' : (highRisk ? 'high' : 'normal'),
      warning: highRisk
    };
  }

  function geometryShapeSignature(node) {
    var geometryAttributes = {
      path: ['d'],
      polygon: ['points'],
      polyline: ['points'],
      rect: ['x', 'y', 'width', 'height', 'rx', 'ry'],
      circle: ['cx', 'cy', 'r'],
      ellipse: ['cx', 'cy', 'rx', 'ry'],
      line: ['x1', 'y1', 'x2', 'y2']
    };
    var localName = String(node.localName || '').toLowerCase();
    var attributes = geometryAttributes[localName] || [];
    return localName + '|' + attributes.map(function (name) {
      return String(node.getAttribute(name) || '').trim().replace(/\s+/g, ' ');
    }).join('|') + '|transform:' + String(node.getAttribute('transform') || '').trim().replace(/\s+/g, ' ');
  }

  function pairSeparatedCorelPaintPaths(geometry, classStyles) {
    var parentGroups = new Map();
    geometry.forEach(function (node) {
      if (node.localName === 'line') return;
      var parent = node.parentNode;
      if (!parentGroups.has(parent)) parentGroups.set(parent, new Map());
      var signatureGroups = parentGroups.get(parent);
      var signature = geometryShapeSignature(node);
      if (!signatureGroups.has(signature)) signatureGroups.set(signature, {outlines: [], fills: []});
      var group = signatureGroups.get(signature);
      var strokeColor = svgColor(inheritedPaint(node, 'stroke', 'none', classStyles), null);
      var fillColor = svgColor(inheritedPaint(node, 'fill', 'none', classStyles), null);
      if (strokeColor && !fillColor) group.outlines.push(node);
      if (fillColor && !strokeColor) group.fills.push({node: node, color: fillColor});
    });

    parentGroups.forEach(function (signatureGroups) {
      signatureGroups.forEach(function (group) {
        // Corel's separated outline/fill export places both copies in the same
        // group. Pairing only complementary copies with identical geometry avoids
        // merging intentionally repeated artwork elsewhere in the SVG.
        var pairCount = Math.min(group.outlines.length, group.fills.length);
        for (var index = 0; index < pairCount; index += 1) {
          var outlineNode = group.outlines[index];
          var fillEntry = group.fills[index];
          outlineNode.setAttribute('data-zigurat-paired-material', fillEntry.color);
          fillEntry.node.setAttribute('data-zigurat-analysis-ignore', '1');
        }
      });
    });
  }

  function corelExpandedGuideOperation(color) {
    var normalized = svgColor(color, null);
    if (!normalized) return null;
    var channels = colorChannels(normalized);
    if (channels[0] <= 75 && channels[1] <= 75 && channels[2] <= 75) return 'primary';
    var operation = pathOperation(normalized);
    return operation === 'double' || operation === 'pin' ? operation : null;
  }

  function collectSvgGeometryMetrics(root, geometry) {
    var metrics = new Map();
    if (!document.body || !geometry.length) return metrics;
    var host = document.createElement('div');
    host.setAttribute('aria-hidden', 'true');
    host.style.cssText = 'position:fixed;left:-100000px;top:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;z-index:-1';
    var clonedRoot = root.cloneNode(true);
    var viewBox = String(root.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
    var metricWidth = 1000;
    var metricHeight = viewBox.length === 4 && viewBox[2] > 0 && viewBox[3] > 0 ? metricWidth * viewBox[3] / viewBox[2] : 1000;
    clonedRoot.setAttribute('width', String(metricWidth));
    clonedRoot.setAttribute('height', String(metricHeight));
    host.appendChild(clonedRoot);
    document.body.appendChild(host);
    try {
      var clonedGeometry = Array.prototype.slice.call(clonedRoot.querySelectorAll(geometrySelector()));
      geometry.forEach(function (node, index) {
        var clone = clonedGeometry[index];
        if (!clone || typeof clone.getBBox !== 'function') return;
        var box;
        try {
          box = clone.getBBox();
        } catch (error) {
          return;
        }
        if (!(box.width > 0 && box.height > 0)) return;
        var filledSamples = 0;
        var sampleCount = 0;
        if (typeof clone.isPointInFill === 'function' && typeof clonedRoot.createSVGPoint === 'function') {
          var gridSize = 14;
          for (var sampleY = 0; sampleY < gridSize; sampleY += 1) {
            for (var sampleX = 0; sampleX < gridSize; sampleX += 1) {
              var point = clonedRoot.createSVGPoint();
              point.x = box.x + ((sampleX + 0.5) * box.width / gridSize);
              point.y = box.y + ((sampleY + 0.5) * box.height / gridSize);
              sampleCount += 1;
              try {
                if (clone.isPointInFill(point)) filledSamples += 1;
              } catch (error) {}
            }
          }
        }
        metrics.set(node, {
          x: box.x,
          y: box.y,
          width: box.width,
          height: box.height,
          right: box.x + box.width,
          bottom: box.y + box.height,
          fillRatio: sampleCount ? filledSamples / sampleCount : null
        });
      });
    } finally {
      host.remove();
    }
    return metrics;
  }

  function unionGeometryBoxes(nodes, metrics) {
    var available = nodes.map(function (node) { return metrics.get(node); }).filter(Boolean);
    if (!available.length) return null;
    var left = Math.min.apply(null, available.map(function (box) { return box.x; }));
    var top = Math.min.apply(null, available.map(function (box) { return box.y; }));
    var right = Math.max.apply(null, available.map(function (box) { return box.right; }));
    var bottom = Math.max.apply(null, available.map(function (box) { return box.bottom; }));
    return {x: left, y: top, right: right, bottom: bottom, width: right - left, height: bottom - top};
  }

  function boxesDescribeSameArtwork(guide, material) {
    if (!guide || !material || !(guide.width > 0 && guide.height > 0)) return false;
    var tolerance = Math.max(2, Math.max(guide.width, guide.height) * 0.008);
    var closeEdges = Math.abs(guide.x - material.x) <= tolerance
      && Math.abs(guide.y - material.y) <= tolerance
      && Math.abs(guide.right - material.right) <= tolerance
      && Math.abs(guide.bottom - material.bottom) <= tolerance;
    var comparableSize = material.width >= guide.width * 0.72 && material.height >= guide.height * 0.72;
    return closeEdges && comparableSize;
  }

  function pairExpandedCorelOutlineObjects(root, geometry, classStyles) {
    var metrics = collectSvgGeometryMetrics(root, geometry);
    if (!metrics.size) return;
    var parentGroups = new Map();
    geometry.forEach(function (node) {
      if (!parentGroups.has(node.parentNode)) parentGroups.set(node.parentNode, []);
      parentGroups.get(node.parentNode).push(node);
    });

    parentGroups.forEach(function (siblings) {
      siblings.forEach(function (guideNode) {
        if (guideNode.getAttribute('data-zigurat-analysis-ignore') === '1') return;
        var guideStroke = svgColor(inheritedPaint(guideNode, 'stroke', 'none', classStyles), null);
        var guideFill = svgColor(inheritedPaint(guideNode, 'fill', 'none', classStyles), null);
        var operation = !guideStroke ? corelExpandedGuideOperation(guideFill) : null;
        var guideMetrics = metrics.get(guideNode);
        if (!operation || !guideMetrics || guideMetrics.fillRatio === null || guideMetrics.fillRatio > 0.16) return;

        var materialNodes = siblings.filter(function (candidate) {
          if (candidate === guideNode || candidate.getAttribute('data-zigurat-analysis-ignore') === '1') return false;
          var fillColor = svgColor(inheritedPaint(candidate, 'fill', 'none', classStyles), null);
          return Boolean(fillColor && metrics.has(candidate));
        });
        var materialUnion = unionGeometryBoxes(materialNodes, metrics);
        if (!boxesDescribeSameArtwork(guideMetrics, materialUnion)) return;

        guideNode.setAttribute('data-zigurat-analysis-ignore', '1');
        materialNodes.forEach(function (materialNode) {
          materialNode.setAttribute('data-zigurat-operation-override', operation);
        });
      });
    });
  }

  function prepareSvg(svgText) {
    var parser = new DOMParser();
    var documentNode = parser.parseFromString(svgText, 'image/svg+xml');
    if (documentNode.querySelector('parsererror') || !documentNode.documentElement || documentNode.documentElement.localName !== 'svg') {
      throw new Error('فایل SVG معتبر نیست یا ساختار آن کامل خوانده نشد.');
    }
    var root = documentNode.documentElement;
    var classStyles = svgClassStyles(root);
    if (root.querySelector('text, textPath, tspan')) {
      throw new Error('داخل فایل هنوز متن وجود دارد. ابتدا در Corel تمام نوشته‌ها را به Curve تبدیل و دوباره SVG بگیرید.');
    }
    if (root.querySelector('use')) {
      throw new Error('این SVG از اجزای پیوندی استفاده می‌کند. هنگام خروجی Corel گزینه تبدیل همه اجزا به Curve را فعال کنید.');
    }

    root.querySelectorAll('script,style,foreignObject,image,iframe,audio,video,object,embed,link,animate,animateMotion,animateTransform,set').forEach(function (node) { node.remove(); });
    root.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes || []).forEach(function (attribute) {
        var name = attribute.name.toLowerCase();
        var value = String(attribute.value || '');
        if (name.indexOf('on') === 0 || name === 'href' || name === 'xlink:href' || /url\((?!\s*#)/i.test(value)) {
          node.removeAttribute(attribute.name);
        }
      });
      node.removeAttribute('filter');
      node.removeAttribute('mask');
    });

    var geometry = Array.prototype.slice.call(root.querySelectorAll(geometrySelector()));
    if (!geometry.length) {
      throw new Error('هیچ مسیر قابل برشی در فایل پیدا نشد. طرح را در Corel به Curve تبدیل کنید.');
    }
    pairSeparatedCorelPaintPaths(geometry, classStyles);
    geometry.forEach(function (node) {
      var resolvedStroke = inheritedPaint(node, 'stroke', 'none', classStyles);
      var resolvedFill = inheritedPaint(node, 'fill', '', classStyles);
      node.setAttribute('stroke', resolvedStroke || 'none');
      node.setAttribute('fill', resolvedFill || '#000000');
    });
    pairExpandedCorelOutlineObjects(root, geometry, classStyles);
    var materialJobs = {};
    geometry.forEach(function (node) {
      if (node.getAttribute('data-zigurat-analysis-ignore') === '1') return;
      var sourceStroke = inheritedPaint(node, 'stroke', 'none', classStyles);
      var sourceFill = inheritedPaint(node, 'fill', '#000000', classStyles);
      var operation = node.getAttribute('data-zigurat-operation-override') || pathOperation(sourceStroke);
      var pairedMaterial = svgColor(node.getAttribute('data-zigurat-paired-material'), null);
      var fillColor = pairedMaterial || svgColor(sourceFill, '#000000');
      node.setAttribute('stroke', sourceStroke || 'none');
      node.setAttribute('fill', sourceFill || fillColor);
      node.setAttribute('data-zigurat-operation', operation);
      node.setAttribute('data-zigurat-material', fillColor);
      if (operation !== 'pin' && node.localName !== 'line') {
        var jobKey = operation + '|' + fillColor;
        if (!materialJobs[jobKey]) {
          materialJobs[jobKey] = {key: jobKey, operation: operation, color: fillColor, label: plexiColorName(fillColor)};
        }
      }
    });
    var viewBox = String(root.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);
    var widthMm = parseSvgLength(root.getAttribute('width'));
    var heightMm = parseSvgLength(root.getAttribute('height'));
    if (viewBox.length !== 4 || !viewBox.every(isFinite) || viewBox[2] <= 0 || viewBox[3] <= 0) {
      var fallbackWidth = widthMm > 0 ? widthMm : 1000;
      var fallbackHeight = heightMm > 0 ? heightMm : 1000;
      viewBox = [0, 0, fallbackWidth, fallbackHeight];
      root.setAttribute('viewBox', viewBox.join(' '));
    }
    if (!(widthMm > 0 && heightMm > 0)) {
      widthMm = 0;
      heightMm = 0;
    }

    root.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    root.setAttribute('preserveAspectRatio', 'none');
    return {
      documentNode: documentNode,
      root: root,
      widthMm: widthMm,
      heightMm: heightMm,
      viewBox: viewBox,
      geometryCount: geometry.length,
      complexity: assessSvgComplexity(geometry),
      materialJobs: Object.keys(materialJobs).map(function (key) { return materialJobs[key]; })
    };
  }

  function keepOnlySvgGeometry(root, operation, material) {
    root.querySelectorAll(geometrySelector()).forEach(function (geometry) {
      if (geometry.getAttribute('data-zigurat-analysis-ignore') === '1') {
        geometry.remove();
        return;
      }
      if (operation && geometry.getAttribute('data-zigurat-operation') !== operation) {
        geometry.remove();
        return;
      }
      if (material && geometry.getAttribute('data-zigurat-material') !== material) geometry.remove();
    });
  }

  function measureSvgPerimeter(prepared, widthMm, heightMm, operation) {
    var scaleX = widthMm / prepared.viewBox[2];
    var scaleY = heightMm / prepared.viewBox[3];
    if (!(scaleX > 0 && scaleY > 0)) return 0;

    var host = document.createElement('div');
    host.style.cssText = 'position:fixed;left:0;top:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none;z-index:-1';
    var svg = document.importNode(prepared.root, true);
    keepOnlySvgGeometry(svg, operation || '', '');
    svg.setAttribute('width', String(prepared.viewBox[2]));
    svg.setAttribute('height', String(prepared.viewBox[3]));
    svg.setAttribute('preserveAspectRatio', 'none');
    host.appendChild(svg);
    document.body.appendChild(host);

    var fallbackLength = 0;
    svg.querySelectorAll('path,polygon,polyline,rect,circle,ellipse,line').forEach(function (geometry) {
      if (typeof geometry.getTotalLength !== 'function') return;
      try {
        fallbackLength += geometry.getTotalLength();
      } catch (error) {}
    });
    var fallbackMm = fallbackLength * Math.sqrt(((scaleX * scaleX) + (scaleY * scaleY)) / 2);
    var totalMm = 0;
    try {
      svg.querySelectorAll('path,polygon,polyline,rect,circle,ellipse,line').forEach(function (geometry) {
        if (typeof geometry.getTotalLength !== 'function') return;
        var localLength = geometry.getTotalLength();
        var elementMatrix = geometry.getCTM();
        if (!(localLength > 0) || !elementMatrix) return;

        var matrix = elementMatrix;
        var axisX = Math.hypot(matrix.a * scaleX, matrix.b * scaleY);
        var axisY = Math.hypot(matrix.c * scaleX, matrix.d * scaleY);
        var dot = (matrix.a * scaleX * matrix.c * scaleX) + (matrix.b * scaleY * matrix.d * scaleY);
        var tolerance = Math.max(1e-6, Math.max(axisX, axisY) * 0.0005);
        var uniform = Math.abs(axisX - axisY) <= tolerance;
        var orthogonal = Math.abs(dot) <= Math.max(1e-6, axisX * axisY * 0.0005);

        if (uniform && orthogonal) {
          totalMm += localLength * ((axisX + axisY) / 2);
          return;
        }

        var moveCount = geometry.localName === 'path'
          ? ((String(geometry.getAttribute('d') || '').match(/[Mm]/g) || []).length)
          : 1;
        if (moveCount > 1) {
          totalMm += localLength * Math.sqrt(((axisX * axisX) + (axisY * axisY)) / 2);
          return;
        }

        var estimatedMm = localLength * Math.max(axisX, axisY);
        var segments = Math.max(24, Math.min(50000, Math.ceil(estimatedMm / 0.35)));
        var previous = geometry.getPointAtLength(0);
        var previousX = ((matrix.a * previous.x) + (matrix.c * previous.y) + matrix.e) * scaleX;
        var previousY = ((matrix.b * previous.x) + (matrix.d * previous.y) + matrix.f) * scaleY;
        for (var index = 1; index <= segments; index += 1) {
          var point = geometry.getPointAtLength(localLength * index / segments);
          var pointX = ((matrix.a * point.x) + (matrix.c * point.y) + matrix.e) * scaleX;
          var pointY = ((matrix.b * point.x) + (matrix.d * point.y) + matrix.f) * scaleY;
          totalMm += Math.hypot(pointX - previousX, pointY - previousY);
          previousX = pointX;
          previousY = pointY;
        }
      });
    } catch (error) {
      return fallbackMm;
    } finally {
      host.remove();
    }
    return totalMm > 0 ? totalMm : fallbackMm;
  }

  function readFile(file) {
    if (!file) return Promise.reject(new Error('ابتدا فایل SVG طرح حروف را انتخاب کنید.'));
    if (file.size > 5 * 1024 * 1024) return Promise.reject(new Error('حجم فایل بیشتر از ۵ مگابایت است.'));
    if (cachedFile === file && cachedText) return Promise.resolve(cachedText);
    return file.text().then(function (text) {
      cachedFile = file;
      cachedText = text;
      return text;
    });
  }

  function clearFilePreview() {
    if (filePreviewUrl) URL.revokeObjectURL(filePreviewUrl);
    filePreviewUrl = '';
    var preview = form.querySelector('[data-letter-file-preview]');
    var image = form.querySelector('[data-letter-source-image]');
    if (image) image.removeAttribute('src');
    if (preview) preview.hidden = true;
  }

  function showFilePreview(prepared, file) {
    var preview = form.querySelector('[data-letter-file-preview]');
    var image = form.querySelector('[data-letter-source-image]');
    var details = form.querySelector('[data-letter-source-details]');
    if (!preview || !image) return;
    clearFilePreview();
    var previewDocument = prepared.documentNode.cloneNode(true);
    var previewRoot = previewDocument.documentElement;
    previewRoot.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    previewRoot.setAttribute('width', prepared.viewBox[2]);
    previewRoot.setAttribute('height', prepared.viewBox[3]);
    filePreviewUrl = URL.createObjectURL(new Blob([new XMLSerializer().serializeToString(previewDocument)], {type: 'image/svg+xml'}));
    image.src = filePreviewUrl;
    image.alt = 'پیش‌نمایش ' + (file.name || 'فایل SVG');
    if (details) {
      var sizeText = prepared.widthMm > 0 && prepared.heightMm > 0
        ? formatMeasure(prepared.widthMm) + ' × ' + formatMeasure(prepared.heightMm) + ' میلی‌متر'
        : 'ابعاد واقعی را در فیلدهای پایین وارد کنید';
      var complexity = prepared.complexity || {};
      var complexityText = complexity.warning
        ? ' — ⚠️ احتمال کندی: ' + Number(complexity.pathCount || 0).toLocaleString('fa-IR') + ' مسیر جدا؛ برای سرعت بیشتر مسیرهای هم‌رنگ را در Corel Combine کنید'
        : ' — پیچیدگی عادی';
      details.classList.toggle('is-warning', Boolean(complexity.warning));
      details.textContent = (file.name || 'فایل SVG') + ' — ' + sizeText + ' — ✓ منحنی تأیید شد (' + Number(prepared.geometryCount || 0).toLocaleString('fa-IR') + ' مسیر برداری)' + complexityText;
    }
    preview.hidden = false;
  }

  function renderSvg(prepared, widthMm, heightMm, job) {
    var maximumPixels = 3200000;
    var targetStep = Math.max(0.75, Math.sqrt((widthMm * heightMm) / maximumPixels));
    var pixelWidth = Math.max(1, Math.round(widthMm / targetStep));
    var pixelHeight = Math.max(1, Math.round(heightMm / targetStep));
    if (pixelWidth * pixelHeight > maximumPixels) {
      var reduction = Math.sqrt((pixelWidth * pixelHeight) / maximumPixels);
      pixelWidth = Math.max(1, Math.floor(pixelWidth / reduction));
      pixelHeight = Math.max(1, Math.floor(pixelHeight / reduction));
    }
    var renderDocument = prepared.documentNode.cloneNode(true);
    var renderRoot = renderDocument.documentElement;
    keepOnlySvgGeometry(renderRoot, job.operation, job.color);
    renderRoot.setAttribute('width', pixelWidth);
    renderRoot.setAttribute('height', pixelHeight);
    var forcedStyle = renderDocument.createElementNS('http://www.w3.org/2000/svg', 'style');
    forcedStyle.textContent = 'path,polygon,polyline,rect,circle,ellipse{fill:#000!important;stroke:none!important;opacity:1!important;fill-opacity:1!important}g{opacity:1!important}';
    renderRoot.insertBefore(forcedStyle, renderRoot.firstChild);
    var serialized = new XMLSerializer().serializeToString(renderDocument);
    var blobUrl = URL.createObjectURL(new Blob([serialized], {type: 'image/svg+xml'}));
    return new Promise(function (resolve, reject) {
      var image = new Image();
      image.onload = function () {
        try {
          var canvas = document.createElement('canvas');
          canvas.width = pixelWidth;
          canvas.height = pixelHeight;
          var context = canvas.getContext('2d', {willReadFrequently: true});
          context.clearRect(0, 0, pixelWidth, pixelHeight);
          context.drawImage(image, 0, 0, pixelWidth, pixelHeight);
          var data = context.getImageData(0, 0, pixelWidth, pixelHeight).data;
          resolve({data: data, width: pixelWidth, height: pixelHeight, resolutionX: widthMm / pixelWidth, resolutionY: heightMm / pixelHeight, job: job});
        } catch (error) {
          reject(new Error('تصویر مسیرهای SVG قابل تحلیل نیست. فایل را دوباره از Corel خروجی بگیرید.'));
        } finally {
          URL.revokeObjectURL(blobUrl);
        }
      };
      image.onerror = function () {
        URL.revokeObjectURL(blobUrl);
        reject(new Error('مرورگر نتوانست مسیرهای SVG را نمایش دهد.'));
      };
      image.src = blobUrl;
    });
  }

  function extractComponents(rendered) {
    var width = rendered.width;
    var height = rendered.height;
    var total = width * height;
    var binary = new Uint8Array(total);
    var filledTotal = 0;
    for (var pixel = 0; pixel < total; pixel += 1) {
      if (rendered.data[(pixel * 4) + 3] > 28) {
        binary[pixel] = 1;
        filledTotal += 1;
      }
    }
    if (!filledTotal) throw new Error('مسیر قابل مشاهده‌ای در SVG پیدا نشد. رنگ یا ساختار Curveها را بررسی کنید.');
    if (filledTotal / total > 0.86) throw new Error('تقریباً تمام صفحه پر است. مستطیل پس‌زمینه یا کادر صفحه را از فایل Corel حذف کنید.');

    var labels = new Int32Array(total);
    var queue = new Int32Array(total);
    var components = [];
    var label = 0;
    for (var index = 0; index < total; index += 1) {
      if (!binary[index] || labels[index]) continue;
      label += 1;
      var head = 0;
      var tail = 1;
      queue[0] = index;
      labels[index] = label;
      var minX = width;
      var minY = height;
      var maxX = 0;
      var maxY = 0;
      var count = 0;
      var perimeterMm = 0;
      while (head < tail) {
        var current = queue[head++];
        var x = current % width;
        var y = (current - x) / width;
        count += 1;
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;

        if (x === 0 || !binary[current - 1]) perimeterMm += rendered.resolutionY;
        else if (!labels[current - 1]) { labels[current - 1] = label; queue[tail++] = current - 1; }
        if (x === width - 1 || !binary[current + 1]) perimeterMm += rendered.resolutionY;
        else if (!labels[current + 1]) { labels[current + 1] = label; queue[tail++] = current + 1; }
        if (y === 0 || !binary[current - width]) perimeterMm += rendered.resolutionX;
        else if (!labels[current - width]) { labels[current - width] = label; queue[tail++] = current - width; }
        if (y === height - 1 || !binary[current + width]) perimeterMm += rendered.resolutionX;
        else if (!labels[current + width]) { labels[current + width] = label; queue[tail++] = current + width; }
      }
      var areaMm2 = count * rendered.resolutionX * rendered.resolutionY;
      if (areaMm2 >= 1) {
        components.push({label: label, minX: minX, minY: minY, maxX: maxX, maxY: maxY, count: count, areaMm2: areaMm2, perimeterMm: perimeterMm});
      }
    }
    if (!components.length) throw new Error('قطعه‌ای با اندازه قابل محاسبه پیدا نشد.');

    components.forEach(function (component) {
      var componentWidth = component.maxX - component.minX + 1;
      var componentHeight = component.maxY - component.minY + 1;
      var mask = new Uint8Array(componentWidth * componentHeight);
      for (var y = component.minY; y <= component.maxY; y += 1) {
        for (var x = component.minX; x <= component.maxX; x += 1) {
          if (labels[(y * width) + x] === component.label) {
            mask[((y - component.minY) * componentWidth) + (x - component.minX)] = 1;
          }
        }
      }
      component.width = componentWidth;
      component.height = componentHeight;
      component.mask = mask;
      component.operation = rendered.job ? rendered.job.operation : 'primary';
      component.materialColor = rendered.job ? rendered.job.color : '#000000';
      component.materialKey = component.materialColor;
      component.materialLabel = rendered.job ? rendered.job.label : plexiColorName(component.materialColor);
    });
    return components;
  }

  function mergeRenderedJobs(renderedJobs, operation) {
    var selected = renderedJobs.filter(function (rendered) {
      return rendered.job && rendered.job.operation === operation;
    });
    if (!selected.length) return null;
    var base = selected[0];
    var mergedData = new Uint8ClampedArray(base.width * base.height * 4);
    selected.forEach(function (rendered) {
      for (var pixel = 0; pixel < base.width * base.height; pixel += 1) {
        if (rendered.data[(pixel * 4) + 3] <= 28) continue;
        mergedData[pixel * 4] = 35;
        mergedData[(pixel * 4) + 1] = 35;
        mergedData[(pixel * 4) + 2] = 35;
        mergedData[(pixel * 4) + 3] = 255;
      }
    });
    return Object.assign({}, base, {
      data: mergedData,
      job: {operation: 'lighting', color: '#ded8ca', label: 'سطح روشنایی'}
    });
  }

  function allocateSmdCounts(components, requestedCount) {
    if (!components.length || requestedCount <= 0) return [];
    var total = Math.max(components.length, Math.ceil(requestedCount));
    var counts = components.map(function () { return 1; });
    var remaining = total - components.length;
    if (remaining <= 0) return counts;
    var totalArea = components.reduce(function (sum, component) { return sum + component.areaMm2; }, 0) || 1;
    var fractions = [];
    var assigned = 0;
    components.forEach(function (component, index) {
      var exact = remaining * component.areaMm2 / totalArea;
      var whole = Math.floor(exact);
      counts[index] += whole;
      assigned += whole;
      fractions.push({index: index, remainder: exact - whole, area: component.areaMm2});
    });
    fractions.sort(function (left, right) {
      return (right.remainder - left.remainder) || (right.area - left.area) || (left.index - right.index);
    });
    for (var extra = 0; extra < remaining - assigned; extra += 1) {
      counts[fractions[extra % fractions.length].index] += 1;
    }
    return counts;
  }

  function bestModuleAngle(component, point, resolutionX, resolutionY, moduleSpec) {
    if (!moduleSpec || ['block', 'lens'].indexOf(moduleSpec.type) === -1) return {angle: 0, fit: 1};
    var widthPixels = Math.max(1, moduleSpec.widthMm / resolutionX);
    var heightPixels = Math.max(1, moduleSpec.heightMm / resolutionY);
    var best = {angle: 0, fit: -1};
    for (var angle = 0; angle < 180; angle += 15) {
      var radians = angle * Math.PI / 180;
      var cosine = Math.cos(radians);
      var sine = Math.sin(radians);
      var inside = 0;
      var tested = 0;
      for (var across = -3; across <= 3; across += 1) {
        for (var down = -1; down <= 1; down += 1) {
          var localX = (across / 6) * widthPixels;
          var localY = (down / 2) * heightPixels;
          var sampleX = Math.round(point.x + (localX * cosine) - (localY * sine));
          var sampleY = Math.round(point.y + (localX * sine) + (localY * cosine));
          tested += 1;
          if (sampleX >= 0 && sampleY >= 0 && sampleX < component.width && sampleY < component.height && component.mask[(sampleY * component.width) + sampleX]) {
            inside += 1;
          }
        }
      }
      var fit = tested ? inside / tested : 0;
      if (fit > best.fit) best = {angle: angle, fit: fit};
    }
    return best;
  }

  function sampleComponentPoints(component, requestedCount, resolutionX, resolutionY, moduleSpec) {
    var target = Math.max(1, Math.min(component.count, Math.ceil(requestedCount)));
    var spacing = Math.max(1, Math.sqrt(component.count / target));
    var best = [];
    var scales = [1.18, 1.08, 1, 0.92, 0.84, 0.76, 0.68];
    var offsets = [0.18, 0.42, 0.68, 0.88];

    scales.forEach(function (factor) {
      var step = Math.max(1, spacing * factor);
      offsets.forEach(function (offset) {
        var candidates = [];
        var row = 0;
        for (var y = offset * step; y < component.height; y += step) {
          var stagger = row % 2 ? step / 2 : 0;
          for (var x = (offset * step) + stagger; x < component.width; x += step) {
            var pixelX = Math.max(0, Math.min(component.width - 1, Math.round(x)));
            var pixelY = Math.max(0, Math.min(component.height - 1, Math.round(y)));
            if (component.mask[(pixelY * component.width) + pixelX]) {
              candidates.push({x: pixelX, y: pixelY});
            }
          }
          row += 1;
        }
        if (!best.length || Math.abs(candidates.length - target) < Math.abs(best.length - target) || (candidates.length >= target && best.length < target)) {
          best = candidates;
        }
      });
    });

    if (best.length > target) {
      var reduced = [];
      var interval = best.length / target;
      for (var pointIndex = 0; pointIndex < target; pointIndex += 1) {
        reduced.push(best[Math.min(best.length - 1, Math.floor((pointIndex + 0.5) * interval))]);
      }
      best = reduced;
    }

    if (best.length < target) {
      var used = {};
      best.forEach(function (point) { used[point.x + ':' + point.y] = true; });
      var fallbackStep = Math.max(1, Math.floor(Math.sqrt(component.count / Math.max(target * 6, 1))));
      for (var fallbackY = 0; fallbackY < component.height && best.length < target; fallbackY += fallbackStep) {
        for (var fallbackX = 0; fallbackX < component.width && best.length < target; fallbackX += fallbackStep) {
          if (!component.mask[(fallbackY * component.width) + fallbackX]) continue;
          var key = fallbackX + ':' + fallbackY;
          if (used[key]) continue;
          used[key] = true;
          best.push({x: fallbackX, y: fallbackY});
        }
      }
    }

    if (!best.length) best.push({x: Math.round(component.width / 2), y: Math.round(component.height / 2)});
    return best.map(function (point) {
      var orientation = bestModuleAngle(component, point, resolutionX, resolutionY, moduleSpec);
      return {
        xMm: (component.minX + point.x + 0.5) * resolutionX,
        yMm: (component.minY + point.y + 0.5) * resolutionY,
        component: component.label,
        angle: orientation.angle,
        fit: orientation.fit
      };
    });
  }

  function buildSmdLayout(state, smdType, density, moduleSpec) {
    if (smdType === 'none' || density <= 0 || !state.lightingComponents || !state.lightingComponents.length) {
      return {key: 'none', points: [], count: 0, densityCount: 0, componentCount: 0};
    }
    var densityCount = Math.ceil(((state.lightingAreaMm2 || state.areaMm2) / 1000000) * density);
    var key = [smdType, density, densityCount, state.lightingComponents.length, Math.round(state.lightingAreaMm2 || 0), moduleSpec.widthMm, moduleSpec.heightMm, moduleSpec.ledCount].join(':');
    if (state.smdLayout && state.smdLayout.key === key) return state.smdLayout;
    var counts = allocateSmdCounts(state.lightingComponents, densityCount);
    var points = [];
    state.lightingComponents.forEach(function (component, index) {
      points = points.concat(sampleComponentPoints(component, counts[index], state.renderResolutionX, state.renderResolutionY, moduleSpec));
    });
    state.smdLayout = {
      key: key,
      points: points,
      count: points.length,
      densityCount: densityCount,
      componentCount: state.lightingComponents.length,
      module: moduleSpec
    };
    return state.smdLayout;
  }

  function thinBinaryMask(source, width, height) {
    var mask = new Uint8Array(source);
    var changed = true;
    var iterations = 0;
    function pixel(x, y) {
      return x < 0 || y < 0 || x >= width || y >= height ? 0 : mask[(y * width) + x];
    }
    function thinningPass(secondPass) {
      var remove = [];
      for (var y = 1; y < height - 1; y += 1) {
        for (var x = 1; x < width - 1; x += 1) {
          if (!pixel(x, y)) continue;
          var p2 = pixel(x, y - 1), p3 = pixel(x + 1, y - 1), p4 = pixel(x + 1, y), p5 = pixel(x + 1, y + 1);
          var p6 = pixel(x, y + 1), p7 = pixel(x - 1, y + 1), p8 = pixel(x - 1, y), p9 = pixel(x - 1, y - 1);
          var neighbours = p2 + p3 + p4 + p5 + p6 + p7 + p8 + p9;
          if (neighbours < 2 || neighbours > 6) continue;
          var transitions = (!p2 && p3) + (!p3 && p4) + (!p4 && p5) + (!p5 && p6)
            + (!p6 && p7) + (!p7 && p8) + (!p8 && p9) + (!p9 && p2);
          if (transitions !== 1) continue;
          if (!secondPass) {
            if (p2 * p4 * p6 || p4 * p6 * p8) continue;
          } else if (p2 * p4 * p8 || p2 * p6 * p8) {
            continue;
          }
          remove.push((y * width) + x);
        }
      }
      remove.forEach(function (index) { mask[index] = 0; });
      return remove.length > 0;
    }
    var maximumIterations = Math.max(width, height) + 4;
    while (changed && iterations < maximumIterations) {
      changed = thinningPass(false);
      changed = thinningPass(true) || changed;
      iterations += 1;
    }
    return mask;
  }

  function distanceFromMaskEdge(mask, width, height, cellWidthMm, cellHeightMm) {
    var distances = new Float32Array(mask.length);
    var diagonal = Math.hypot(cellWidthMm, cellHeightMm);
    var infinity = 1000000000;
    for (var index = 0; index < mask.length; index += 1) distances[index] = mask[index] ? infinity : 0;
    for (var y = 0; y < height; y += 1) {
      for (var x = 0; x < width; x += 1) {
        var position = (y * width) + x;
        if (!mask[position]) continue;
        var value = distances[position];
        value = Math.min(value, x > 0 ? distances[position - 1] + cellWidthMm : cellWidthMm);
        value = Math.min(value, y > 0 ? distances[position - width] + cellHeightMm : cellHeightMm);
        value = Math.min(value, x > 0 && y > 0 ? distances[position - width - 1] + diagonal : diagonal);
        value = Math.min(value, x < width - 1 && y > 0 ? distances[position - width + 1] + diagonal : diagonal);
        distances[position] = value;
      }
    }
    for (var reverseY = height - 1; reverseY >= 0; reverseY -= 1) {
      for (var reverseX = width - 1; reverseX >= 0; reverseX -= 1) {
        var reversePosition = (reverseY * width) + reverseX;
        if (!mask[reversePosition]) continue;
        var reverseValue = distances[reversePosition];
        reverseValue = Math.min(reverseValue, reverseX < width - 1 ? distances[reversePosition + 1] + cellWidthMm : cellWidthMm);
        reverseValue = Math.min(reverseValue, reverseY < height - 1 ? distances[reversePosition + width] + cellHeightMm : cellHeightMm);
        reverseValue = Math.min(reverseValue, reverseX < width - 1 && reverseY < height - 1 ? distances[reversePosition + width + 1] + diagonal : diagonal);
        reverseValue = Math.min(reverseValue, reverseX > 0 && reverseY < height - 1 ? distances[reversePosition + width - 1] + diagonal : diagonal);
        distances[reversePosition] = reverseValue;
      }
    }
    return distances;
  }

  function pruneShortSkeletonBranches(source, width, height, maximumSteps) {
    var skeleton = new Uint8Array(source);
    function neighbours(index) {
      var x = index % width;
      var y = Math.floor(index / width);
      var result = [];
      for (var offsetY = -1; offsetY <= 1; offsetY += 1) {
        for (var offsetX = -1; offsetX <= 1; offsetX += 1) {
          if (!offsetX && !offsetY) continue;
          var nextX = x + offsetX;
          var nextY = y + offsetY;
          if (nextX < 0 || nextY < 0 || nextX >= width || nextY >= height) continue;
          var nextIndex = (nextY * width) + nextX;
          if (!skeleton[nextIndex]) continue;
          if (offsetX && offsetY && (skeleton[(y * width) + nextX] || skeleton[(nextY * width) + x])) continue;
          result.push(nextIndex);
        }
      }
      return result;
    }
    for (var pass = 0; pass < 3; pass += 1) {
      var endpoints = [];
      skeleton.forEach(function (value, index) {
        if (value && neighbours(index).length === 1) endpoints.push(index);
      });
      var remove = {};
      endpoints.forEach(function (endpoint) {
        var path = [endpoint];
        var previous = -1;
        var current = endpoint;
        var reachedJunction = false;
        while (path.length <= maximumSteps) {
          var available = neighbours(current).filter(function (index) { return index !== previous; });
          var degree = neighbours(current).length;
          if (current !== endpoint && degree > 2) {
            reachedJunction = true;
            break;
          }
          if (!available.length) break;
          previous = current;
          current = available[0];
          path.push(current);
        }
        if (!reachedJunction || path.length > maximumSteps + 1) return;
        path.slice(0, -1).forEach(function (index) { remove[index] = true; });
      });
      var indexes = Object.keys(remove);
      if (!indexes.length) break;
      indexes.forEach(function (index) { skeleton[Number(index)] = 0; });
    }
    return skeleton;
  }

  function buildRoolookiComponent(component, resolutionX, resolutionY, options) {
    var stripWidthMm = options.stripWidthMm;
    var cutIntervalMm = options.cutIntervalMm;
    var wastePercent = options.wastePercent;
    var componentWidthMm = component.width * resolutionX;
    var componentHeightMm = component.height * resolutionY;
    var cellMm = Math.max(2.5, Math.min(6, stripWidthMm * 0.45), componentWidthMm / 360, componentHeightMm / 360);
    var gridWidth = Math.max(3, Math.min(360, Math.ceil(componentWidthMm / cellMm)));
    var gridHeight = Math.max(3, Math.min(360, Math.ceil(componentHeightMm / cellMm)));
    var cellWidthMm = componentWidthMm / gridWidth;
    var cellHeightMm = componentHeightMm / gridHeight;
    var grid = new Uint8Array(gridWidth * gridHeight);
    var filled = 0;
    for (var y = 0; y < gridHeight; y += 1) {
      var sourceY = Math.min(component.height - 1, Math.floor(((y + 0.5) / gridHeight) * component.height));
      for (var x = 0; x < gridWidth; x += 1) {
        var sourceX = Math.min(component.width - 1, Math.floor(((x + 0.5) / gridWidth) * component.width));
        if (!component.mask[(sourceY * component.width) + sourceX]) continue;
        grid[(y * gridWidth) + x] = 1;
        filled += 1;
      }
    }
    if (!filled) return {segments: [], rawLengthMm: 0, installedLengthMm: 0, purchaseLengthMm: 0, multiTrackLengthMm: 0, maximumLaneCount: 1, clearanceLimited: true};
    var distances = distanceFromMaskEdge(grid, gridWidth, gridHeight, cellWidthMm, cellHeightMm);
    var rasterBoundaryCorrection = Math.max(cellWidthMm, cellHeightMm) / 2;
    distances.forEach(function (distance, index) {
      if (grid[index]) distances[index] = Math.max(0, distance - rasterBoundaryCorrection);
    });
    var requiredCenterClearance = options.wallClearanceMm + (stripWidthMm / 2);
    var maximumClearance = 0;
    distances.forEach(function (distance, index) {
      if (grid[index]) maximumClearance = Math.max(maximumClearance, distance);
    });
    var effectiveClearance = Math.min(requiredCenterClearance, Math.max(stripWidthMm / 2, maximumClearance - Math.max(cellWidthMm, cellHeightMm)));
    var safeGrid = new Uint8Array(grid.length);
    var safeCount = 0;
    grid.forEach(function (value, index) {
      if (value && distances[index] >= effectiveClearance) {
        safeGrid[index] = 1;
        safeCount += 1;
      }
    });
    if (!safeCount) {
      var maximumIndex = 0;
      distances.forEach(function (distance, index) { if (distance > distances[maximumIndex]) maximumIndex = index; });
      safeGrid[maximumIndex] = 1;
    }
    var skeleton = thinBinaryMask(safeGrid, gridWidth, gridHeight);
    var pruneSteps = Math.max(4, Math.ceil(maximumClearance / Math.max(cellWidthMm, cellHeightMm)) + 2);
    skeleton = pruneShortSkeletonBranches(skeleton, gridWidth, gridHeight, pruneSteps);
    var segments = [];
    var rawLengthMm = 0;
    var multiTrackLengthMm = 0;
    var maximumLaneCount = 1;
    var directions = [[1, 0], [0, 1], [1, 1], [-1, 1]];
    function pointAt(x, y) {
      return {
        xMm: (component.minX * resolutionX) + ((x + 0.5) * cellWidthMm),
        yMm: (component.minY * resolutionY) + ((y + 0.5) * cellHeightMm)
      };
    }
    function smoothedTangentAt(centerX, centerY, fallbackX, fallbackY) {
      var radius = 4;
      var points = [];
      for (var nearbyY = Math.max(0, centerY - radius); nearbyY <= Math.min(gridHeight - 1, centerY + radius); nearbyY += 1) {
        for (var nearbyX = Math.max(0, centerX - radius); nearbyX <= Math.min(gridWidth - 1, centerX + radius); nearbyX += 1) {
          if (!skeleton[(nearbyY * gridWidth) + nearbyX]) continue;
          if (Math.hypot(nearbyX - centerX, nearbyY - centerY) > radius) continue;
          points.push({x: nearbyX * cellWidthMm, y: nearbyY * cellHeightMm});
        }
      }
      if (points.length < 3) return {x: fallbackX, y: fallbackY};
      var meanX = points.reduce(function (sum, point) { return sum + point.x; }, 0) / points.length;
      var meanY = points.reduce(function (sum, point) { return sum + point.y; }, 0) / points.length;
      var covarianceXX = 0;
      var covarianceXY = 0;
      var covarianceYY = 0;
      points.forEach(function (point) {
        var deltaX = point.x - meanX;
        var deltaY = point.y - meanY;
        covarianceXX += deltaX * deltaX;
        covarianceXY += deltaX * deltaY;
        covarianceYY += deltaY * deltaY;
      });
      var angle = 0.5 * Math.atan2(2 * covarianceXY, covarianceXX - covarianceYY);
      return {x: Math.cos(angle), y: Math.sin(angle)};
    }
    for (var skeletonY = 0; skeletonY < gridHeight; skeletonY += 1) {
      for (var skeletonX = 0; skeletonX < gridWidth; skeletonX += 1) {
        if (!skeleton[(skeletonY * gridWidth) + skeletonX]) continue;
        directions.forEach(function (direction) {
          var nextX = skeletonX + direction[0];
          var nextY = skeletonY + direction[1];
          if (nextX < 0 || nextY < 0 || nextX >= gridWidth || nextY >= gridHeight || !skeleton[(nextY * gridWidth) + nextX]) return;
          if (direction[0] !== 0 && direction[1] !== 0) {
            var horizontalBridge = skeleton[(skeletonY * gridWidth) + nextX];
            var verticalBridge = skeleton[(nextY * gridWidth) + skeletonX];
            if (horizontalBridge || verticalBridge) return;
          }
          var start = pointAt(skeletonX, skeletonY);
          var end = pointAt(nextX, nextY);
          var lengthMm = Math.hypot(end.xMm - start.xMm, end.yMm - start.yMm);
          if (!lengthMm) return;
          var localClearance = Math.min(distances[(skeletonY * gridWidth) + skeletonX], distances[(nextY * gridWidth) + nextX]);
          var usableSpan = Math.max(0, 2 * (localClearance - requiredCenterClearance));
          var sideSafetyMm = Math.max(3, stripWidthMm * 0.35);
          var workingSpan = Math.max(0, usableSpan - (2 * sideSafetyMm));
          var minimumTwoLaneSpan = Math.max(stripWidthMm * 1.5, options.rowSpacingMm * 0.5);
          var laneCount = workingSpan >= minimumTwoLaneSpan
            ? Math.min(4, Math.ceil(workingSpan / options.rowSpacingMm) + 1)
            : 1;
          if (laneCount > 1) {
            var laneSpan = Math.min(workingSpan, (laneCount - 1) * options.rowSpacingMm);
            var actualSpacing = laneSpan / (laneCount - 1);
            var fallbackTangentX = (end.xMm - start.xMm) / lengthMm;
            var fallbackTangentY = (end.yMm - start.yMm) / lengthMm;
            var tangent = smoothedTangentAt(Math.round((skeletonX + nextX) / 2), Math.round((skeletonY + nextY) / 2), fallbackTangentX, fallbackTangentY);
            var unitNormalX = -tangent.y;
            var unitNormalY = tangent.x;
            for (var laneIndex = 0; laneIndex < laneCount; laneIndex += 1) {
              var laneOffset = (-laneSpan / 2) + (laneIndex * actualSpacing);
              var normalX = unitNormalX * laneOffset;
              var normalY = unitNormalY * laneOffset;
              segments.push({x1Mm: start.xMm + normalX, y1Mm: start.yMm + normalY, x2Mm: end.xMm + normalX, y2Mm: end.yMm + normalY, component: component.label, lane: laneIndex + 1, laneCount: laneCount});
            }
            rawLengthMm += lengthMm * laneCount;
            multiTrackLengthMm += lengthMm;
            maximumLaneCount = Math.max(maximumLaneCount, laneCount);
          } else {
            segments.push({x1Mm: start.xMm, y1Mm: start.yMm, x2Mm: end.xMm, y2Mm: end.yMm, component: component.label, lane: 0});
            rawLengthMm += lengthMm;
          }
        });
      }
    }
    if (!segments.length) {
      var fallbackIndex = skeleton.findIndex(function (value) { return value === 1; });
      var fallbackX = fallbackIndex >= 0 ? fallbackIndex % gridWidth : Math.floor(gridWidth / 2);
      var fallbackY = fallbackIndex >= 0 ? Math.floor(fallbackIndex / gridWidth) : Math.floor(gridHeight / 2);
      var center = pointAt(fallbackX, fallbackY);
      var fallbackLength = Math.max(1, Math.min(cutIntervalMm, cellWidthMm * 0.8, cellHeightMm * 0.8));
      segments.push({x1Mm: center.xMm - (fallbackLength / 2), y1Mm: center.yMm, x2Mm: center.xMm + (fallbackLength / 2), y2Mm: center.yMm, component: component.label});
      rawLengthMm = fallbackLength;
    }
    var installedLengthMm = Math.ceil(rawLengthMm / cutIntervalMm) * cutIntervalMm;
    var purchaseLengthMm = Math.ceil((installedLengthMm * (1 + (wastePercent / 100))) / cutIntervalMm) * cutIntervalMm;
    return {
      segments: segments,
      rawLengthMm: rawLengthMm,
      installedLengthMm: installedLengthMm,
      purchaseLengthMm: purchaseLengthMm,
      multiTrackLengthMm: multiTrackLengthMm,
      maximumLaneCount: maximumLaneCount,
      clearanceLimited: maximumClearance < requiredCenterClearance
    };
  }

  function buildRoolookiLayout(state, options) {
    if (!state.lightingComponents || !state.lightingComponents.length) {
      return {key: 'roll:none', type: 'roll', points: [], segments: [], count: 0, densityCount: 0, componentCount: 0, installedLengthMm: 0, purchaseLengthMm: 0, rollCount: 0, powerWatts: 0, multiTrackLengthMm: 0, maximumLaneCount: 1};
    }
    var key = ['roll', options.stripWidthMm, options.wallClearanceMm, options.rowSpacingMm, options.cutIntervalMm, options.wastePercent, options.rollLengthM, options.wattsPerMeter, state.lightingComponents.length, Math.round(state.lightingAreaMm2 || 0)].join(':');
    if (state.smdLayout && state.smdLayout.key === key) return state.smdLayout;
    var segments = [];
    var rawLengthMm = 0;
    var installedLengthMm = 0;
    var purchaseLengthMm = 0;
    var multiTrackLengthMm = 0;
    var maximumLaneCount = 1;
    var clearanceLimitedComponents = 0;
    state.lightingComponents.forEach(function (component) {
      var route = buildRoolookiComponent(component, state.renderResolutionX, state.renderResolutionY, options);
      segments = segments.concat(route.segments);
      rawLengthMm += route.rawLengthMm;
      installedLengthMm += route.installedLengthMm;
      purchaseLengthMm += route.purchaseLengthMm;
      multiTrackLengthMm += route.multiTrackLengthMm;
      maximumLaneCount = Math.max(maximumLaneCount, route.maximumLaneCount || 1);
      if (route.clearanceLimited) clearanceLimitedComponents += 1;
    });
    state.smdLayout = {
      key: key,
      type: 'roll',
      points: [],
      segments: segments,
      count: 0,
      densityCount: 0,
      componentCount: state.lightingComponents.length,
      stripWidthMm: options.stripWidthMm,
      rawLengthMm: rawLengthMm,
      installedLengthMm: installedLengthMm,
      purchaseLengthMm: purchaseLengthMm,
      rollCount: purchaseLengthMm > 0 ? Math.ceil((purchaseLengthMm / 1000) / options.rollLengthM) : 0,
      powerWatts: (installedLengthMm / 1000) * options.wattsPerMeter,
      multiTrackLengthMm: multiTrackLengthMm,
      maximumLaneCount: maximumLaneCount,
      clearanceLimitedComponents: clearanceLimitedComponents,
      rollLengthM: options.rollLengthM,
      cutIntervalMm: options.cutIntervalMm,
      wastePercent: options.wastePercent
    };
    return state.smdLayout;
  }

  function buildComponentPreview(component, color) {
    var canvas = document.createElement('canvas');
    canvas.width = component.width;
    canvas.height = component.height;
    var context = canvas.getContext('2d');
    var imageData = context.createImageData(component.width, component.height);
    var red = parseInt(color.slice(1, 3), 16);
    var green = parseInt(color.slice(3, 5), 16);
    var blue = parseInt(color.slice(5, 7), 16);
    for (var index = 0; index < component.mask.length; index += 1) {
      if (!component.mask[index]) continue;
      imageData.data[index * 4] = red;
      imageData.data[(index * 4) + 1] = green;
      imageData.data[(index * 4) + 2] = blue;
      imageData.data[(index * 4) + 3] = 235;
    }
    context.putImageData(imageData, 0, 0);
    return canvas;
  }

  function addPackingProfiles(item) {
    var rows = [];
    var cellCount = 0;
    for (var visualY = 0; visualY < item.height; visualY += 1) {
      var runs = [];
      var runStart = -1;
      for (var x = 0; x < item.width; x += 1) {
        var filled = item.collisionMask[(visualY * item.width) + x] === 1;
        if (filled) {
          cellCount += 1;
          if (runStart < 0) runStart = x;
        } else if (runStart >= 0) {
          runs.push([runStart, x - 1]);
          runStart = -1;
        }
      }
      if (runStart >= 0) runs.push([runStart, item.width - 1]);
      if (runs.length) rows.push({y: item.height - 1 - visualY, runs: runs});
    }
    item.packingRows = rows;
    item.cellCount = cellCount;
    return item;
  }

  function makePackingItem(component, rendered, stepMm, gapMm, index) {
    var rawWidth = Math.max(1, Math.ceil(component.width * rendered.resolutionX / stepMm));
    var rawHeight = Math.max(1, Math.ceil(component.height * rendered.resolutionY / stepMm));
    var raw = new Uint8Array(rawWidth * rawHeight);
    for (var y = 0; y < component.height; y += 1) {
      for (var x = 0; x < component.width; x += 1) {
        if (!component.mask[(y * component.width) + x]) continue;
        var gridX = Math.min(rawWidth - 1, Math.floor(x * rendered.resolutionX / stepMm));
        var gridY = Math.min(rawHeight - 1, Math.floor(y * rendered.resolutionY / stepMm));
        raw[(gridY * rawWidth) + gridX] = 1;
      }
    }
    var padding = Math.max(0, Math.ceil((gapMm / 2) / stepMm));
    var width = rawWidth + (2 * padding);
    var height = rawHeight + (2 * padding);
    var collision = new Uint8Array(width * height);
    for (var rawY = 0; rawY < rawHeight; rawY += 1) {
      for (var rawX = 0; rawX < rawWidth; rawX += 1) {
        if (!raw[(rawY * rawWidth) + rawX]) continue;
        var drawX = rawX + padding;
        var drawY = rawY + padding;
        for (var offsetY = -padding; offsetY <= padding; offsetY += 1) {
          for (var offsetX = -padding; offsetX <= padding; offsetX += 1) {
            if ((offsetX * offsetX) + (offsetY * offsetY) > (padding * padding) + 0.5) continue;
            var collisionX = drawX + offsetX;
            var collisionY = drawY + offsetY;
            var collisionIndex = (collisionY * width) + collisionX;
            if (!collision[collisionIndex]) {
              collision[collisionIndex] = 1;
            }
          }
        }
      }
    }
    var palette = ['#b98722', '#287a68', '#8a4c88', '#346ca8', '#b45145', '#6b7e2f'];
    return addPackingProfiles({
      id: index,
      width: width,
      height: height,
      collisionMask: collision,
      areaMm2: component.areaMm2,
      perimeterMm: component.perimeterMm,
      rotation: 0,
      padding: padding,
      component: component,
      previewCanvas: buildComponentPreview(component, component.previewColor || component.materialColor || palette[index % palette.length]),
      previewWidthMm: component.width * rendered.resolutionX,
      previewHeightMm: component.height * rendered.resolutionY
    });
  }

  function rotateItem(item, angle) {
    var normalizedAngle = ((angle % 360) + 360) % 360;
    if (normalizedAngle === 0) return item;
    var radians = normalizedAngle * Math.PI / 180;
    var cosine = Math.cos(radians);
    var sine = Math.sin(radians);
    var absoluteCosine = Math.abs(cosine) < 0.0000001 ? 0 : Math.abs(cosine);
    var absoluteSine = Math.abs(sine) < 0.0000001 ? 0 : Math.abs(sine);
    var width = Math.max(1, Math.ceil((item.width * absoluteCosine) + (item.height * absoluteSine)));
    var height = Math.max(1, Math.ceil((item.width * absoluteSine) + (item.height * absoluteCosine)));
    var collision = new Uint8Array(width * height);
    var sourceCenterX = (item.width - 1) / 2;
    var sourceCenterY = (item.height - 1) / 2;
    var targetCenterX = (width - 1) / 2;
    var targetCenterY = (height - 1) / 2;
    for (var targetY = 0; targetY < height; targetY += 1) {
      for (var targetX = 0; targetX < width; targetX += 1) {
        var deltaX = targetX - targetCenterX;
        var deltaY = targetY - targetCenterY;
        var sourceX = (cosine * deltaX) + (sine * deltaY) + sourceCenterX;
        var sourceY = (-sine * deltaX) + (cosine * deltaY) + sourceCenterY;
        var sourceFloorX = Math.floor(sourceX);
        var sourceFloorY = Math.floor(sourceY);
        var found = false;
        for (var sampleY = sourceFloorY; sampleY <= sourceFloorY + 1 && !found; sampleY += 1) {
          if (sampleY < 0 || sampleY >= item.height) continue;
          for (var sampleX = sourceFloorX; sampleX <= sourceFloorX + 1; sampleX += 1) {
            if (sampleX < 0 || sampleX >= item.width) continue;
            if (item.collisionMask[(sampleY * item.width) + sampleX]) {
              found = true;
              break;
            }
          }
        }
        if (found) collision[(targetY * width) + targetX] = 1;
      }
    }
    return addPackingProfiles({
      id: item.id,
      width: width,
      height: height,
      collisionMask: collision,
      areaMm2: item.areaMm2,
      perimeterMm: item.perimeterMm,
      rotation: normalizedAngle,
      padding: item.padding,
      component: item.component,
      previewCanvas: item.previewCanvas,
      previewWidthMm: item.previewWidthMm,
      previewHeightMm: item.previewHeightMm
    });
  }

  function buildOrientations(item, allowRotation) {
    if (!allowRotation) return [item];
    var orientations = [];
    for (var angle = 0; angle < 360; angle += 10) orientations.push(rotateItem(item, angle));
    return orientations;
  }

  function createSheet(width, height) {
    var rowPrefix = [];
    for (var y = 0; y < height; y += 1) rowPrefix.push(new Uint16Array(width + 1));
    return {
      width: width,
      height: height,
      occupied: new Uint8Array(width * height),
      rowPrefix: rowPrefix,
      placements: [],
      usedBottom: 0,
      usedRight: 0
    };
  }

  function canPlace(sheet, item, startX, startY) {
    if (startY >= sheet.usedBottom) return true;
    for (var rowIndex = 0; rowIndex < item.packingRows.length; rowIndex += 1) {
      var itemRow = item.packingRows[rowIndex];
      var sheetY = startY + itemRow.y;
      if (sheetY >= sheet.usedBottom) continue;
      var prefix = sheet.rowPrefix[sheetY];
      for (var runIndex = 0; runIndex < itemRow.runs.length; runIndex += 1) {
        var run = itemRow.runs[runIndex];
        var from = startX + run[0];
        var to = startX + run[1] + 1;
        if (prefix[to] - prefix[from] > 0) return false;
      }
    }
    return true;
  }

  function findPlacement(sheet, orientations) {
    var best = null;
    var orderedOrientations = orientations.slice().sort(function (a, b) {
      return (a.width * a.height) - (b.width * b.height) || a.height - b.height || a.width - b.width;
    });
    for (var orientationIndex = 0; orientationIndex < orderedOrientations.length; orientationIndex += 1) {
      var item = orderedOrientations[orientationIndex];
      if (item.width > sheet.width || item.height > sheet.height) continue;
      for (var y = 0; y <= sheet.height - item.height; y += 1) {
        var usedHeight = Math.max(sheet.usedBottom, y + item.height);
        var minimumUsedRight = Math.max(sheet.usedRight, item.width);
        if (best && (usedHeight * minimumUsedRight) > best.envelopeArea) break;
        for (var x = 0; x <= sheet.width - item.width; x += 1) {
          if (!canPlace(sheet, item, x, y)) continue;
          var usedRight = Math.max(sheet.usedRight, x + item.width);
          var envelopeArea = usedHeight * usedRight;
          var isBetter = !best
            || envelopeArea < best.envelopeArea
            || (envelopeArea === best.envelopeArea && usedHeight < best.usedHeight)
            || (envelopeArea === best.envelopeArea && usedHeight === best.usedHeight && usedRight < best.usedRight)
            || (envelopeArea === best.envelopeArea && usedHeight === best.usedHeight && usedRight === best.usedRight && y < best.y)
            || (envelopeArea === best.envelopeArea && usedHeight === best.usedHeight && usedRight === best.usedRight && y === best.y && x < best.x);
          if (isBetter) {
            best = {x: x, y: y, item: item, envelopeArea: envelopeArea, usedHeight: usedHeight, usedRight: usedRight};
            if (sheet.placements.length && usedHeight === sheet.usedBottom && usedRight === sheet.usedRight) return best;
          }
        }
      }
    }
    return best;
  }

  function occupy(sheet, placement) {
    var changedRows = {};
    placement.item.packingRows.forEach(function (itemRow) {
      var sheetY = placement.y + itemRow.y;
      itemRow.runs.forEach(function (run) {
        var from = placement.x + run[0];
        var to = placement.x + run[1];
        for (var x = from; x <= to; x += 1) sheet.occupied[(sheetY * sheet.width) + x] = 1;
      });
      changedRows[sheetY] = true;
    });
    Object.keys(changedRows).forEach(function (rowKey) {
      var row = parseInt(rowKey, 10);
      var prefix = sheet.rowPrefix[row];
      prefix[0] = 0;
      for (var x = 0; x < sheet.width; x += 1) prefix[x + 1] = prefix[x] + sheet.occupied[(row * sheet.width) + x];
    });
    sheet.placements.push(placement);
    sheet.usedBottom = Math.max(sheet.usedBottom, placement.y + placement.item.height);
    sheet.usedRight = Math.max(sheet.usedRight, placement.x + placement.item.width);
  }

  function packInOrder(items, sheetWidth, sheetHeight, allowRotation, sorter) {
    var ordered = items.slice().sort(sorter);
    var sheets = [];
    var unplaced = [];
    ordered.forEach(function (baseItem) {
      var orientations = baseItem.orientations || buildOrientations(baseItem, allowRotation);
      var selected = null;
      var selectedSheet = null;
      // Fill sheets sequentially. Once a second sheet exists, later pieces must
      // still try every earlier sheet first so usable gaps are not abandoned.
      for (var sheetIndex = 0; sheetIndex < sheets.length; sheetIndex += 1) {
        var candidate = findPlacement(sheets[sheetIndex], orientations);
        if (!candidate) continue;
        selected = candidate;
        selectedSheet = sheets[sheetIndex];
        break;
      }
      if (!selected) {
        selectedSheet = createSheet(sheetWidth, sheetHeight);
        selected = findPlacement(selectedSheet, orientations);
        if (!selected) {
          unplaced.push(baseItem);
          return;
        }
        sheets.push(selectedSheet);
      }
      occupy(selectedSheet, selected);
    });
    sheets.unplaced = unplaced;
    return sheets;
  }

  function seededItemRank(item, seed) {
    var value = Math.imul((item.id + 1) ^ seed, 2654435761) >>> 0;
    value ^= value >>> 16;
    value = Math.imul(value, 2246822519) >>> 0;
    value ^= value >>> 13;
    return value >>> 0;
  }

  function createSeededSorter(seed) {
    return function (a, b) {
      var rankDifference = seededItemRank(a, seed) - seededItemRank(b, seed);
      return rankDifference || b.cellCount - a.cellCount || a.id - b.id;
    };
  }

  function packItems(items, sheetWidth, sheetHeight, allowRotation, requestedTrials) {
    var trialCount = [5, 10, 20, 30].indexOf(Number(requestedTrials)) !== -1 ? Number(requestedTrials) : 10;
    var sorters = [
      function (a, b) { return b.cellCount - a.cellCount; },
      function (a, b) { return Math.max(b.width, b.height) - Math.max(a.width, a.height); },
      function (a, b) { return b.height - a.height || b.width - a.width; },
      function (a, b) { return b.width - a.width || b.height - a.height; },
      function (a, b) { return b.perimeterMm - a.perimeterMm; }
    ];
    while (sorters.length < trialCount) {
      sorters.push(createSeededSorter(7919 + (sorters.length * 104729)));
    }
    items.forEach(function (item) { item.orientations = buildOrientations(item, allowRotation); });
    var best = null;
    var sequence = Promise.resolve();
    sorters.forEach(function (sorter, index) {
      sequence = sequence.then(function () {
        updateProgress('مرحله ۳ از ۴: بررسی چیدمان آزمایشی ' + (index + 1).toLocaleString('fa-IR') + ' از ' + sorters.length.toLocaleString('fa-IR'));
        return yieldToBrowser();
      }).then(function () {
        var candidate = packInOrder(items, sheetWidth, sheetHeight, allowRotation, sorter);
        var usedBottom = candidate.reduce(function (sum, sheet) { return sum + sheet.usedBottom; }, 0);
        var unplacedCount = (candidate.unplaced || []).length;
        var score = (unplacedCount * 1000000000000000) + (candidate.length * 1000000000) + usedBottom;
        if (!best || score < best.score) best = {sheets: candidate, score: score};
      });
    });
    return sequence.then(function () { return best.sheets; });
  }

  function packMaterialGroups(items, sheetWidth, sheetHeight, allowRotation, requestedTrials) {
    var groups = {};
    items.forEach(function (item) {
      var key = item.component.materialKey || item.component.materialColor || '#000000';
      if (!groups[key]) {
        groups[key] = {
          key: key,
          color: item.component.materialColor || '#000000',
          label: item.component.materialLabel || plexiColorName(item.component.materialColor || '#000000'),
          items: []
        };
      }
      groups[key].items.push(item);
    });
    var orderedGroups = Object.keys(groups).map(function (key) { return groups[key]; });
    var allSheets = [];
    var allUnplaced = [];
    var sequence = Promise.resolve();
    orderedGroups.forEach(function (group, groupIndex) {
      sequence = sequence.then(function () {
        updateProgress('مرحله ۳ از ۴: چیدمان گروه ' + (groupIndex + 1).toLocaleString('fa-IR') + ' از ' + orderedGroups.length.toLocaleString('fa-IR') + ' — ' + group.label);
        return packItems(group.items, sheetWidth, sheetHeight, allowRotation, requestedTrials);
      }).then(function (sheets) {
        sheets.forEach(function (sheet) {
          sheet.materialKey = group.key;
          sheet.materialColor = group.color;
          sheet.materialLabel = group.label;
        });
        (sheets.unplaced || []).forEach(function (item) {
          item.materialKey = group.key;
          item.materialColor = group.color;
          item.materialLabel = group.label;
          allUnplaced.push(item);
        });
        allSheets = allSheets.concat(sheets);
      });
    });
    return sequence.then(function () {
      allSheets.unplaced = allUnplaced;
      return allSheets;
    });
  }

  function sheetTightBounds(plan, sheet) {
    if (!sheet.placements.length) return {x: 0, y: plan.sheetHeightMm, width: 0, height: 0, area: 0};
    var minimumX = plan.sheetWidthMm;
    var minimumY = plan.sheetHeightMm;
    var maximumX = 0;
    var maximumY = 0;
    sheet.placements.forEach(function (placement) {
      var angle = placement.item.rotation * Math.PI / 180;
      var sourceWidth = placement.item.previewWidthMm;
      var sourceHeight = placement.item.previewHeightMm;
      var rotatedWidth = (Math.abs(Math.cos(angle)) * sourceWidth) + (Math.abs(Math.sin(angle)) * sourceHeight);
      var rotatedHeight = (Math.abs(Math.sin(angle)) * sourceWidth) + (Math.abs(Math.cos(angle)) * sourceHeight);
      var centerX = plan.marginMm + ((placement.x + (placement.item.width / 2)) * plan.stepMm);
      var centerY = plan.sheetHeightMm - plan.marginMm - ((placement.y + (placement.item.height / 2)) * plan.stepMm);
      minimumX = Math.min(minimumX, centerX - (rotatedWidth / 2));
      maximumX = Math.max(maximumX, centerX + (rotatedWidth / 2));
      minimumY = Math.min(minimumY, centerY - (rotatedHeight / 2));
      maximumY = Math.max(maximumY, centerY + (rotatedHeight / 2));
    });
    minimumX = Math.max(0, minimumX);
    minimumY = Math.max(0, minimumY);
    maximumX = Math.min(plan.sheetWidthMm, maximumX);
    maximumY = Math.min(plan.sheetHeightMm, maximumY);
    var width = Math.max(0, maximumX - minimumX);
    var height = Math.max(0, maximumY - minimumY);
    return {x: minimumX, y: minimumY, width: width, height: height, area: width * height};
  }

  function updateSheetConsumption(plan, sheet) {
    var fullHeight = Math.min(plan.sheetHeightMm, plan.marginMm + (sheet.usedBottom * plan.stepMm));
    sheet.fullConsumption = {
      x: 0,
      y: plan.sheetHeightMm - fullHeight,
      width: plan.sheetWidthMm,
      height: fullHeight,
      area: plan.sheetWidthMm * fullHeight
    };
    sheet.tightConsumption = sheetTightBounds(plan, sheet);
    if (sheet.consumptionMode !== 'tight') sheet.consumptionMode = 'full';
    var selected = sheet.consumptionMode === 'tight' ? sheet.tightConsumption : sheet.fullConsumption;
    sheet.consumptionWidthMm = selected.width;
    sheet.consumptionHeightMm = selected.height;
    sheet.consumptionAreaMm2 = selected.area;
  }

  function drawConsumptionRectangle(context, rect, scale, color, fillColor, selected) {
    if (!rect || rect.width <= 0 || rect.height <= 0) return;
    context.save();
    context.fillStyle = selected ? fillColor : 'transparent';
    context.strokeStyle = color;
    context.lineWidth = selected ? Math.max(2.5, 1.8 * window.devicePixelRatio) : Math.max(1.5, window.devicePixelRatio);
    context.setLineDash(selected ? [] : [6, 4]);
    var inset = context.lineWidth / 2;
    context.fillRect(rect.x * scale, rect.y * scale, rect.width * scale, rect.height * scale);
    context.strokeRect((rect.x * scale) + inset, (rect.y * scale) + inset, Math.max(0, (rect.width * scale) - (2 * inset)), Math.max(0, (rect.height * scale) - (2 * inset)));
    context.restore();
  }

  function drawPreview(plan) {
    var container = form.querySelector('[data-letter-sheet-preview]');
    container.innerHTML = '';
    plan.sheets.forEach(function (sheet, sheetIndex) {
      var card = document.createElement('article');
      var title = document.createElement('strong');
      title.textContent = 'ورق ' + (sheetIndex + 1).toLocaleString('fa-IR') + ' — ' + (sheet.materialLabel || 'پلکسی');
      updateSheetConsumption(plan, sheet);
      var modes = document.createElement('div');
      modes.className = 'manager-letter-sheet-modes';
      [
        {key: 'full', label: 'تمام عرض ورق', color: '#df2f2f', rect: sheet.fullConsumption},
        {key: 'tight', label: 'فقط دور حروف', color: '#147d92', rect: sheet.tightConsumption}
      ].forEach(function (option) {
        var label = document.createElement('label');
        label.style.setProperty('--mode-color', option.color);
        var radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'letter-sheet-consumption-' + sheetIndex;
        radio.value = option.key;
        radio.checked = sheet.consumptionMode === option.key;
        if (radio.checked) label.classList.add('is-selected');
        radio.addEventListener('change', function () {
          if (!radio.checked) return;
          sheet.consumptionMode = option.key;
          showAnalysis(plan);
        });
        var swatch = document.createElement('i');
        var text = document.createElement('span');
        text.textContent = option.label + ': ' + formatMeasure(option.rect.width, 0) + ' × ' + formatMeasure(option.rect.height, 0) + ' میلی‌متر';
        label.appendChild(radio);
        label.appendChild(swatch);
        label.appendChild(text);
        modes.appendChild(label);
      });
      var canvas = document.createElement('canvas');
      var maxPreview = 680;
      var scale = Math.min(maxPreview / plan.sheetWidthMm, maxPreview / plan.sheetHeightMm);
      canvas.width = Math.max(180, Math.round(plan.sheetWidthMm * scale));
      canvas.height = Math.max(180, Math.round(plan.sheetHeightMm * scale));
      canvas.setAttribute('aria-label', 'پیش‌نمایش چیدمان ورق ' + (sheetIndex + 1));
      var context = canvas.getContext('2d');
      context.fillStyle = '#f8f6f0';
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.strokeStyle = '#bcae8d';
      context.lineWidth = 1;
      context.setLineDash([5, 4]);
      context.strokeRect(plan.marginMm * scale, plan.marginMm * scale, (plan.sheetWidthMm - 2 * plan.marginMm) * scale, (plan.sheetHeightMm - 2 * plan.marginMm) * scale);
      context.setLineDash([]);
      context.imageSmoothingEnabled = true;
      sheet.placements.forEach(function (placement) {
        var boxLeftMm = plan.marginMm + (placement.x * plan.stepMm);
        var itemBoxTopMm = plan.sheetHeightMm - plan.marginMm - ((placement.y + placement.item.height) * plan.stepMm);
        var boxWidthMm = placement.item.width * plan.stepMm;
        var boxHeightMm = placement.item.height * plan.stepMm;
        var centerX = boxLeftMm + (boxWidthMm / 2);
        var centerY = itemBoxTopMm + (boxHeightMm / 2);
        context.save();
        context.translate(centerX * scale, centerY * scale);
        context.rotate(placement.item.rotation * Math.PI / 180);
        context.drawImage(
          placement.item.previewCanvas,
          -(placement.item.previewWidthMm * scale) / 2,
          -(placement.item.previewHeightMm * scale) / 2,
          placement.item.previewWidthMm * scale,
          placement.item.previewHeightMm * scale
        );
        context.restore();
      });
      drawConsumptionRectangle(context, sheet.fullConsumption, scale, '#df2f2f', 'rgba(220, 45, 45, 0.04)', sheet.consumptionMode === 'full');
      drawConsumptionRectangle(context, sheet.tightConsumption, scale, '#147d92', 'rgba(20, 125, 146, 0.05)', sheet.consumptionMode === 'tight');
      var selectedConsumption = sheet.consumptionMode === 'tight' ? sheet.tightConsumption : sheet.fullConsumption;
      var usage = document.createElement('small');
      usage.className = 'manager-letter-sheet-usage';
      usage.textContent = 'مبنای قیمت: ' + (sheet.consumptionMode === 'tight' ? 'مستطیل فیروزه‌ای دور حروف' : 'مستطیل قرمز تمام‌عرض') + ' — ' + formatMeasure(selectedConsumption.area / 1000000, 3) + ' مترمربع';
      card.appendChild(title);
      card.appendChild(modes);
      card.appendChild(canvas);
      card.appendChild(usage);
      container.appendChild(card);
    });
  }

  function drawUnplacedItems(state) {
    var container = form.querySelector('[data-letter-unplaced]');
    if (!container) return;
    var items = state.unplacedItems || [];
    container.innerHTML = '';
    container.hidden = !items.length;
    if (!items.length) return;
    var heading = document.createElement('strong');
    heading.textContent = items.length.toLocaleString('fa-IR') + ' قطعه داخل ابعاد فعلی ورق جا‌نشد';
    var note = document.createElement('p');
    note.textContent = 'بقیه قطعات چیده شده‌اند. مصرف ورق و قیمت رویه، قطعات جانشده را شامل نمی‌شود.';
    container.appendChild(heading);
    container.appendChild(note);
    items.forEach(function (item, index) {
      var article = document.createElement('article');
      var preview = document.createElement('canvas');
      preview.className = 'manager-letter-unplaced__preview';
      preview.width = 300;
      preview.height = 220;
      var context = preview.getContext('2d');
      context.fillStyle = '#fffdf9';
      context.fillRect(0, 0, preview.width, preview.height);
      var scale = Math.min(270 / item.previewCanvas.width, 185 / item.previewCanvas.height);
      var width = item.previewCanvas.width * scale;
      var height = item.previewCanvas.height * scale;
      var left = (preview.width - width) / 2;
      var top = (preview.height - height) / 2;
      var pieceCanvas = document.createElement('canvas');
      pieceCanvas.width = Math.max(1, Math.ceil(width));
      pieceCanvas.height = Math.max(1, Math.ceil(height));
      var pieceContext = pieceCanvas.getContext('2d');
      pieceContext.drawImage(item.previewCanvas, 0, 0, pieceCanvas.width, pieceCanvas.height);
      pieceContext.globalCompositeOperation = 'source-in';
      pieceContext.fillStyle = '#d9342b';
      pieceContext.fillRect(0, 0, pieceCanvas.width, pieceCanvas.height);
      pieceContext.globalCompositeOperation = 'source-over';
      context.drawImage(pieceCanvas, left, top, width, height);
      context.strokeStyle = '#d63a2e';
      context.lineWidth = 2;
      context.setLineDash([7, 5]);
      context.strokeRect(Math.max(1, left - 3), Math.max(1, top - 3), width + 6, height + 6);
      context.setLineDash([]);
      var info = document.createElement('span');
      var label = document.createElement('b');
      label.textContent = 'قطعه جا‌نشده ' + (index + 1).toLocaleString('fa-IR') + ' — ' + (item.materialLabel || 'پلکسی');
      var size = document.createElement('small');
      size.textContent = formatMeasure(item.previewWidthMm, 0) + ' × ' + formatMeasure(item.previewHeightMm, 0) + ' میلی‌متر';
      info.appendChild(label);
      info.appendChild(size);
      article.appendChild(preview);
      article.appendChild(info);
      container.appendChild(article);
    });
  }

  function roundedRectPath(context, x, y, width, height, radius) {
    var safeRadius = Math.max(0, Math.min(radius, Math.abs(width) / 2, Math.abs(height) / 2));
    context.beginPath();
    context.moveTo(x + safeRadius, y);
    context.lineTo(x + width - safeRadius, y);
    context.quadraticCurveTo(x + width, y, x + width, y + safeRadius);
    context.lineTo(x + width, y + height - safeRadius);
    context.quadraticCurveTo(x + width, y + height, x + width - safeRadius, y + height);
    context.lineTo(x + safeRadius, y + height);
    context.quadraticCurveTo(x, y + height, x, y + height - safeRadius);
    context.lineTo(x, y + safeRadius);
    context.quadraticCurveTo(x, y, x + safeRadius, y);
    context.closePath();
  }

  function drawSmdMarker(context, point, layout, coordinateScaleX, coordinateScaleY) {
    var x = point.xMm * coordinateScaleX;
    var y = point.yMm * coordinateScaleY;
    var module = layout.module || {type: 'none'};
    context.save();
    context.translate(x, y);
    context.rotate((Number(point.angle) || 0) * Math.PI / 180);

    if (module.type === 'block' || module.type === 'lens') {
      var moduleWidth = Math.max(1, module.widthMm * coordinateScaleX);
      var moduleHeight = Math.max(1, module.heightMm * coordinateScaleY);
      var left = -moduleWidth / 2;
      var top = -moduleHeight / 2;
      context.shadowColor = 'rgba(13,45,68,.28)';
      context.shadowBlur = Math.max(1, moduleHeight * 0.12);
      context.shadowOffsetY = Math.max(0.5, moduleHeight * 0.07);
      roundedRectPath(context, left, top, moduleWidth, moduleHeight, Math.max(0.8, moduleHeight * 0.22));
      context.fillStyle = module.type === 'lens' ? '#eef5f8' : '#f7f8f9';
      context.fill();
      context.shadowColor = 'transparent';
      context.strokeStyle = '#526775';
      context.lineWidth = Math.max(0.65, Math.min(1.8, moduleHeight * 0.08));
      context.stroke();

      var ledCount = Math.max(1, Number(module.ledCount) || 3);
      var ledRadius = Math.max(0.55, Math.min(moduleHeight * 0.25, moduleWidth / (ledCount * 3.2)));
      for (var ledIndex = 0; ledIndex < ledCount; ledIndex += 1) {
        var ledX = ledCount === 1 ? 0 : (-moduleWidth * 0.31) + ((moduleWidth * 0.62) * ledIndex / (ledCount - 1));
        context.beginPath();
        context.arc(ledX, 0, ledRadius * (module.type === 'lens' ? 1.7 : 1.45), 0, Math.PI * 2);
        context.fillStyle = module.type === 'lens' ? '#d6eef9' : '#fff3a8';
        context.fill();
        context.beginPath();
        context.arc(ledX, 0, ledRadius, 0, Math.PI * 2);
        context.fillStyle = module.type === 'lens' ? '#f8fcff' : '#f2c230';
        context.fill();
        context.strokeStyle = module.type === 'lens' ? '#4f94b7' : '#b88312';
        context.lineWidth = Math.max(0.35, ledRadius * 0.18);
        context.stroke();
        if (module.type === 'lens') {
          context.beginPath();
          context.arc(ledX - (ledRadius * 0.25), -(ledRadius * 0.25), Math.max(0.25, ledRadius * 0.24), 0, Math.PI * 2);
          context.fillStyle = 'rgba(255,255,255,.95)';
          context.fill();
        }
      }
    } else {
      var pointRadius = Math.max(2.4, Math.min(6.5, Math.sqrt((context.canvas.width * context.canvas.height) / Math.max(layout.points.length, 1)) * 0.055));
      context.beginPath();
      context.arc(0, 0, pointRadius + 1.35, 0, Math.PI * 2);
      context.fillStyle = 'rgba(255,255,255,.96)';
      context.fill();
      context.beginPath();
      context.arc(0, 0, pointRadius, 0, Math.PI * 2);
      context.fillStyle = module.type === 'lens' ? '#8bcbea' : '#1769aa';
      context.fill();
      context.strokeStyle = '#0b3558';
      context.lineWidth = Math.max(0.8, pointRadius * 0.2);
      context.stroke();
    }
    context.restore();
  }

  function drawSmdPreview(state, layout, smdType) {
    var section = form.querySelector('[data-letter-smd-preview]');
    var holder = form.querySelector('[data-letter-smd-canvas]');
    var summary = form.querySelector('[data-letter-smd-layout-summary]');
    if (!section || !holder) return;
    holder.innerHTML = '';
    var hasRoolookiPath = smdType === 'roll' && layout && layout.segments && layout.segments.length;
    if (smdType === 'none' || !layout || (!hasRoolookiPath && !layout.points.length)) {
      section.hidden = true;
      return;
    }

    var sourceWidth = Math.max(1, state.renderWidth || Math.round(state.designWidthMm / state.renderResolutionX));
    var sourceHeight = Math.max(1, state.renderHeight || Math.round(state.designHeightMm / state.renderResolutionY));
    var maxWidth = 1040;
    var maxHeight = 620;
    var scale = Math.min(maxWidth / sourceWidth, maxHeight / sourceHeight);
    var canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(sourceWidth * scale));
    canvas.height = Math.max(1, Math.round(sourceHeight * scale));
    canvas.setAttribute('aria-label', 'چیدمان تقریبی ' + smdLabels[smdType] + ' روی حروف');
    var context = canvas.getContext('2d');
    context.fillStyle = '#f8f6f0';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';

    var displayComponents = (state.sourceComponents || []).slice().sort(function (left, right) {
      var leftPriority = left.operation === 'primary' ? 0 : 1;
      var rightPriority = right.operation === 'primary' ? 0 : 1;
      return leftPriority - rightPriority;
    });
    displayComponents.forEach(function (component) {
      if (!component.smdPreviewCanvas) {
        component.smdPreviewCanvas = buildComponentPreview(component, component.materialColor || '#d6cfbf');
      }
      context.drawImage(
        component.smdPreviewCanvas,
        component.minX * scale,
        component.minY * scale,
        component.width * scale,
        component.height * scale
      );
    });

    if (!displayComponents.length) {
      (state.lightingComponents || []).forEach(function (component) {
        if (!component.smdPreviewCanvas) component.smdPreviewCanvas = buildComponentPreview(component, '#ded8ca');
        context.drawImage(component.smdPreviewCanvas, component.minX * scale, component.minY * scale, component.width * scale, component.height * scale);
      });
    }

    var coordinateScaleX = scale / state.renderResolutionX;
    var coordinateScaleY = scale / state.renderResolutionY;
    if (hasRoolookiPath) {
      var stripScale = (coordinateScaleX + coordinateScaleY) / 2;
      context.save();
      context.lineCap = 'butt';
      context.lineJoin = 'round';
      function strokeRoolookiSegments(color, width) {
        context.beginPath();
        layout.segments.forEach(function (segment) {
          context.moveTo(segment.x1Mm * coordinateScaleX, segment.y1Mm * coordinateScaleY);
          context.lineTo(segment.x2Mm * coordinateScaleX, segment.y2Mm * coordinateScaleY);
        });
        context.strokeStyle = color;
        context.lineWidth = width;
        context.stroke();
      }
      strokeRoolookiSegments('rgba(255,255,255,.96)', Math.max(3, (layout.stripWidthMm * stripScale) + 3));
      strokeRoolookiSegments('#d99c08', Math.max(2, layout.stripWidthMm * stripScale));
      strokeRoolookiSegments('rgba(255,241,168,.88)', Math.max(0.7, layout.stripWidthMm * stripScale * 0.18));
      context.restore();
    } else {
      layout.points.forEach(function (point) {
        drawSmdMarker(context, point, layout, coordinateScaleX, coordinateScaleY);
      });
    }

    holder.appendChild(canvas);
    if (summary) {
      if (smdType === 'roll') {
        summary.textContent = formatMeasure(layout.installedLengthMm / 1000, 2) + ' متر نصب؛ '
          + formatMeasure(layout.purchaseLengthMm / 1000, 2) + ' متر با پرت؛ '
          + layout.rollCount.toLocaleString('fa-IR') + ' رول ' + formatMeasure(layout.rollLengthM, 1) + ' متری؛ '
          + formatMeasure(layout.powerWatts, 1) + ' وات مصرف'
          + (layout.maximumLaneCount > 1 ? '؛ حداکثر ' + layout.maximumLaneCount.toLocaleString('fa-IR') + ' ردیف در قسمت‌های پهن' : '')
          + (layout.clearanceLimitedComponents > 0 ? '؛ هشدار: ' + layout.clearanceLimitedComponents.toLocaleString('fa-IR') + ' قطعه برای حریم انتخابی باریک است' : '');
        section.hidden = false;
        return;
      }
      var minimumNote = layout.count > layout.densityCount
        ? '؛ به‌دلیل قطعات مستقل، ' + (layout.count - layout.densityCount).toLocaleString('fa-IR') + ' عدد به حداقل محاسباتی افزوده شد'
        : '';
      var moduleNote = smdType === 'block' || smdType === 'lens'
        ? '؛ بلوک ' + (smdType === 'lens' ? 'لنزدار ' : '') + formatMeasure(layout.module.widthMm, 1) + ' × ' + formatMeasure(layout.module.heightMm, 1) + ' میلی‌متر، ' + layout.module.ledCount.toLocaleString('fa-IR') + ' چیپ'
        : '';
      summary.textContent = layout.count.toLocaleString('fa-IR') + ' واحد روی ' + layout.componentCount.toLocaleString('fa-IR') + ' قطعه مستقل' + moduleNote + minimumNote;
    }
    section.hidden = false;
  }

  function transformerSetting(source, watts, suffix, fallback) {
    var name = 'transformer_' + watts + '_' + suffix;
    if (source && Object.prototype.hasOwnProperty.call(source, name)) {
      return suffix === 'rate' ? money(source[name]) : Math.max(1, Math.round(decimal(source[name])));
    }
    var input = rateField(name);
    if (!input) return fallback;
    return suffix === 'rate' ? money(input.value) : Math.max(1, Math.round(decimal(input.value)));
  }

  function preferTransformerQuantities(candidate, current) {
    for (var index = 0; index < candidate.length; index += 1) {
      if (candidate[index] !== current[index]) return candidate[index] > current[index];
    }
    return false;
  }

  function recommendTransformers(smdCount, sourceRates) {
    var target = Math.max(0, Math.ceil(Number(smdCount) || 0));
    var fallbackCapacities = {400: 330, 300: 250, 200: 160, 120: 90, 100: 70, 60: 40};
    var types = transformerWatts.map(function (watts) {
      return {
        watts: watts,
        capacity: transformerSetting(sourceRates, watts, 'capacity', fallbackCapacities[watts]),
        rate: transformerSetting(sourceRates, watts, 'rate', 0)
      };
    });
    if (!target) return {items: [], count: 0, capacity: 0, cost: 0};

    var maximumCapacity = types.reduce(function (maximum, type) { return Math.max(maximum, type.capacity); }, 1);
    var limit = target + maximumCapacity - 1;
    var plans = new Array(limit + 1);
    plans[0] = {count: 0, quantities: types.map(function () { return 0; })};
    for (var capacity = 0; capacity <= limit; capacity += 1) {
      var plan = plans[capacity];
      if (!plan) continue;
      types.forEach(function (type, typeIndex) {
        var nextCapacity = capacity + type.capacity;
        if (nextCapacity > limit) return;
        var quantities = plan.quantities.slice();
        quantities[typeIndex] += 1;
        var candidate = {count: plan.count + 1, quantities: quantities};
        var existing = plans[nextCapacity];
        if (!existing || candidate.count < existing.count || (candidate.count === existing.count && preferTransformerQuantities(candidate.quantities, existing.quantities))) {
          plans[nextCapacity] = candidate;
        }
      });
    }

    var selected = null;
    for (var totalCapacity = target; totalCapacity <= limit; totalCapacity += 1) {
      var possible = plans[totalCapacity];
      if (!possible) continue;
      if (!selected
        || possible.count < selected.count
        || (possible.count === selected.count && totalCapacity < selected.capacity)
        || (possible.count === selected.count && totalCapacity === selected.capacity && preferTransformerQuantities(possible.quantities, selected.quantities))) {
        selected = {count: possible.count, capacity: totalCapacity, quantities: possible.quantities};
      }
    }
    if (!selected) return {items: [], count: 0, capacity: 0, cost: 0};
    var items = [];
    var totalCost = 0;
    selected.quantities.forEach(function (quantity, index) {
      if (!quantity) return;
      var type = types[index];
      var cost = quantity * type.rate;
      totalCost += cost;
      items.push({watts: type.watts, capacity: type.capacity, rate: type.rate, count: quantity, cost: cost});
    });
    return {items: items, count: selected.count, capacity: selected.capacity, cost: totalCost};
  }

  function recommendTransformersByWatts(requiredWatts, reservePercent, sourceRates) {
    var actualWatts = Math.max(0, Number(requiredWatts) || 0);
    var reserve = Math.max(0, Math.min(50, Number(reservePercent) || 0));
    var target = Math.max(0, Math.ceil(actualWatts * (1 + (reserve / 100))));
    var types = transformerWatts.map(function (watts) {
      return {watts: watts, capacity: watts, rate: transformerSetting(sourceRates, watts, 'rate', 0)};
    });
    if (!target) return {items: [], count: 0, capacity: 0, cost: 0, basis: 'watts', requiredWatts: 0, reservePercent: reserve};
    var maximumCapacity = types[0].capacity;
    var limit = target + maximumCapacity - 1;
    var plans = new Array(limit + 1);
    plans[0] = {count: 0, quantities: types.map(function () { return 0; })};
    for (var capacity = 0; capacity <= limit; capacity += 1) {
      var plan = plans[capacity];
      if (!plan) continue;
      types.forEach(function (type, typeIndex) {
        var nextCapacity = capacity + type.capacity;
        if (nextCapacity > limit) return;
        var quantities = plan.quantities.slice();
        quantities[typeIndex] += 1;
        var candidate = {count: plan.count + 1, quantities: quantities};
        var existing = plans[nextCapacity];
        if (!existing || candidate.count < existing.count || (candidate.count === existing.count && preferTransformerQuantities(candidate.quantities, existing.quantities))) {
          plans[nextCapacity] = candidate;
        }
      });
    }
    var selected = null;
    for (var totalCapacity = target; totalCapacity <= limit; totalCapacity += 1) {
      var possible = plans[totalCapacity];
      if (!possible) continue;
      if (!selected || possible.count < selected.count || (possible.count === selected.count && totalCapacity < selected.capacity)
        || (possible.count === selected.count && totalCapacity === selected.capacity && preferTransformerQuantities(possible.quantities, selected.quantities))) {
        selected = {count: possible.count, capacity: totalCapacity, quantities: possible.quantities};
      }
    }
    if (!selected) return {items: [], count: 0, capacity: 0, cost: 0, basis: 'watts', requiredWatts: actualWatts, reservePercent: reserve};
    var items = [];
    var totalCost = 0;
    selected.quantities.forEach(function (quantity, index) {
      if (!quantity) return;
      var type = types[index];
      var cost = quantity * type.rate;
      totalCost += cost;
      items.push({watts: type.watts, capacity: type.capacity, rate: type.rate, count: quantity, cost: cost});
    });
    return {items: items, count: selected.count, capacity: selected.capacity, cost: totalCost, basis: 'watts', requiredWatts: actualWatts, reservePercent: reserve};
  }

  function transformerPlanText(plan) {
    if (!plan || !plan.items || !plan.items.length) return 'بدون SMD';
    var composition = plan.items.map(function (item) {
      return item.count.toLocaleString('fa-IR') + ' × ' + item.watts.toLocaleString('fa-IR') + ' وات';
    }).join(' + ');
    if (plan.basis === 'watts') {
      return composition + '؛ توان لازم ' + formatMeasure(plan.requiredWatts, 1) + ' وات؛ ظرفیت مجموع ' + plan.capacity.toLocaleString('fa-IR') + ' وات با ' + formatMeasure(plan.reservePercent, 0) + '٪ رزرو';
    }
    return composition + '؛ ظرفیت مجموع ' + plan.capacity.toLocaleString('fa-IR') + ' عدد SMD';
  }

  function calculateCosts() {
    if (!analysisState) return;
    var state = analysisState;
    var plexiSquareMeterRate = money(rateField('plexi_sqm_rate').value);
    var metalSheet07SquareMeterRate = money(rateField('metal_sheet_07_sqm_rate').value);
    var edgeType = edgeLabels[field('edge_type').value] ? field('edge_type').value : 'swedish';
    var edgeRate = money(rateField('edge_' + edgeType + '_material_rate').value);
    var edgeLaborRate = money(rateField('edge_' + edgeType + '_labor_rate').value);
    var powderCoatingRate = money(rateField('metal_powder_coating_rate').value);
    var doubleLayerLaborRate = money(rateField('double_layer_labor_rate').value);
    var pvcRate = money(rateField('pvc_rate').value);
    var plexiCutRate = money(rateField('plexi_cut_rate').value);
    var pvcCutRate = money(rateField('pvc_cut_rate').value);
    var glueRate = money(rateField('glue_rate').value);
    var smdType = smdLabels[field('smd_type').value] ? field('smd_type').value : 'none';
    var smdRateField = smdType !== 'none' ? rateField('smd_' + smdType + '_rate') : null;
    var smdRate = smdRateField ? money(smdRateField.value) : 0;
    var smdDensityField = smdType !== 'none' && smdType !== 'roll' ? rateField('smd_' + smdType + '_units_per_square_meter') : null;
    var smdDensity = smdDensityField ? decimal(smdDensityField.value) : 0;
    var sizedSmdType = smdType === 'block' || smdType === 'lens' ? smdType : '';
    var smdModuleSpec = sizedSmdType ? {
      type: sizedSmdType,
      widthMm: Math.max(10, decimal(rateField('smd_' + sizedSmdType + '_width_mm').value) || 75),
      heightMm: Math.max(5, decimal(rateField('smd_' + sizedSmdType + '_height_mm').value) || 15),
      ledCount: Math.max(1, Math.min(8, Math.round(decimal(rateField('smd_' + sizedSmdType + '_led_count').value) || 3)))
    } : {type: smdType, widthMm: 0, heightMm: 0, ledCount: 1};
    var roolookiOptions = smdType === 'roll' ? {
      stripWidthMm: Math.max(2, decimal(rateField('smd_roll_strip_width_mm').value) || 10),
      wattsPerMeter: Math.max(0.1, decimal(rateField('smd_roll_watts_per_meter').value) || 12),
      rollLengthM: Math.max(0.1, decimal(rateField('smd_roll_length_m').value) || 10),
      cutIntervalMm: Math.max(1, decimal(rateField('smd_roll_cut_interval_mm').value) || 10),
      wallClearanceMm: Math.max(0, decimal(rateField('smd_roll_wall_clearance_mm').value)),
      rowSpacingMm: Math.max(10, decimal(rateField('smd_roll_row_spacing_mm').value) || 50),
      wastePercent: Math.max(0, decimal(rateField('smd_roll_waste_percent').value)),
      transformerReservePercent: Math.max(0, decimal(rateField('smd_roll_transformer_reserve_percent').value))
    } : null;
    var areaSquareMeters = state.areaMm2 / 1000000;
    var lightingAreaSquareMeters = (state.lightingAreaMm2 || state.areaMm2) / 1000000;
    var consumedSquareMeters = state.consumedAreaMm2 / 1000000;
    var perimeterMeters = roundUpToOneDecimal(state.perimeterMm / 1000);
    var doublePerimeterMeters = roundUpToOneDecimal(state.doublePerimeterMm / 1000);
    var pinPerimeterMeters = roundUpToOneDecimal(state.pinPerimeterMm / 1000);
    var laserPerimeterMeters = roundUpToOneDecimal(state.laserPerimeterMm / 1000);
    var usesMetalFace = edgeType === 'metal';
    var plexiCost = usesMetalFace ? 0 : Math.round(consumedSquareMeters * plexiSquareMeterRate);
    var metalSheet07Cost = usesMetalFace ? Math.round(consumedSquareMeters * metalSheet07SquareMeterRate) : 0;
    var powderCoatingCost = usesMetalFace ? Math.round(areaSquareMeters * powderCoatingRate) : 0;
    var edgeCost = Math.round(perimeterMeters * edgeRate);
    var buildCost = Math.round(perimeterMeters * edgeLaborRate);
    var doubleLaborCost = Math.round(doublePerimeterMeters * doubleLayerLaborRate);
    var plexiCutCost = usesMetalFace ? 0 : Math.round(laserPerimeterMeters * plexiCutRate);
    var pvcCost = Math.round(consumedSquareMeters * pvcRate);
    var pvcCutCost = Math.round(perimeterMeters * pvcCutRate);
    var glueCost = Math.round(perimeterMeters * glueRate);
    var smdLayout = smdType === 'roll'
      ? buildRoolookiLayout(state, roolookiOptions)
      : buildSmdLayout(state, smdType, smdDensity, smdModuleSpec);
    var smdCount = smdLayout.count;
    var smdInstalledLengthMeters = smdType === 'roll' ? smdLayout.installedLengthMm / 1000 : 0;
    var smdPurchaseLengthMeters = smdType === 'roll' ? smdLayout.purchaseLengthMm / 1000 : 0;
    var smdCost = smdType === 'roll' ? Math.round(smdPurchaseLengthMeters * smdRate) : smdCount * smdRate;
    var installationMode = field('installation_mode').value === 'perimeter' ? 'perimeter' : 'fixed';
    var installationRate = money(field('installation').value);
    var installation = installationMode === 'perimeter' ? Math.round(perimeterMeters * installationRate) : installationRate;
    var travel = money(field('travel').value);
    var useTransformer = field('use_transformer').checked;
    var transformerPlan = useTransformer
      ? (smdType === 'roll'
        ? recommendTransformersByWatts(smdLayout.powerWatts, roolookiOptions.transformerReservePercent)
        : recommendTransformers(smdCount))
      : {items: [], count: 0, capacity: 0, cost: 0};
    var transformerCount = transformerPlan.count;
    var transformer = transformerPlan.cost;
    var wireSupplies = money(field('wire_supplies').value);
    var extras = installation + travel + wireSupplies;
    var base = plexiCost + metalSheet07Cost + powderCoatingCost + edgeCost + buildCost + doubleLaborCost + plexiCutCost + pvcCost + pvcCutCost + glueCost + smdCost + transformer + extras;
    var profitPercent = Math.min(1000, decimal(field('profit_percent').value));
    var profit = Math.round(base * profitPercent / 100);
    var afterProfit = base + profit;
    var insuranceTaxPercent = Math.min(1000, decimal(field('insurance_tax_percent').value));
    var insuranceTax = Math.round(afterProfit * insuranceTaxPercent / 100);
    var finalPrice = afterProfit + insuranceTax;

    currentCalculation = {
      plexi: plexiCost,
      metal_sheet_07: metalSheet07Cost,
      powder_coating: powderCoatingCost,
      edge: edgeCost,
      edge_labor: buildCost,
      double_labor: doubleLaborCost,
      plexi_cut: plexiCutCost,
      pvc: pvcCost,
      pvc_cut: pvcCutCost,
      glue: glueCost,
      smd: smdCost,
      smd_count: smdCount,
      smd_density_count: smdLayout.densityCount,
      smd_component_count: smdLayout.componentCount,
      smd_length_m: smdInstalledLengthMeters,
      smd_purchase_length_m: smdPurchaseLengthMeters,
      smd_roll_count: smdType === 'roll' ? smdLayout.rollCount : 0,
      smd_power_watts: smdType === 'roll' ? smdLayout.powerWatts : 0,
      smd_double_track_length_m: smdType === 'roll' ? smdLayout.multiTrackLengthMm / 1000 : 0,
      smd_multi_track_length_m: smdType === 'roll' ? smdLayout.multiTrackLengthMm / 1000 : 0,
      smd_max_lane_count: smdType === 'roll' ? smdLayout.maximumLaneCount : 0,
      installation: installation,
      travel: travel,
      transformer: transformer,
      use_transformer: useTransformer ? 1 : 0,
      transformer_count: transformerCount,
      transformer_capacity: transformerPlan.capacity,
      wire_supplies: wireSupplies,
      base: base,
      profit: profit,
      insurance_tax_percent: insuranceTaxPercent,
      insurance_tax: insuranceTax,
      final: finalPrice,
      rounded_perimeter_m: perimeterMeters,
      double_perimeter_m: doublePerimeterMeters,
      pin_perimeter_m: pinPerimeterMeters,
      laser_perimeter_m: laserPerimeterMeters,
      area_square_meters: areaSquareMeters,
      lighting_area_square_meters: lightingAreaSquareMeters,
      consumed_square_meters: consumedSquareMeters
    };

    setText('[data-letter-plexi-cost]', usesMetalFace ? 'محاسبه نمی‌شود' : formatMeasure(consumedSquareMeters, 3) + ' مترمربع — ' + formatMoney(plexiCost));
    setText('[data-letter-metal-sheet-cost]', usesMetalFace ? formatMeasure(consumedSquareMeters, 3) + ' مترمربع — ' + formatMoney(metalSheet07Cost) : 'محاسبه نمی‌شود');
    setText('[data-letter-powder-coating-cost]', usesMetalFace ? formatMeasure(areaSquareMeters, 3) + ' مترمربع — ' + formatMoney(powderCoatingCost) : 'محاسبه نمی‌شود');
    setText('[data-letter-edge-label]', 'قیمت ' + edgeLabels[edgeType]);
    setText('[data-letter-edge-labor-label]', 'اجرت ساخت ' + edgeLabels[edgeType]);
    setText('[data-letter-edge-cost]', formatMeasure(perimeterMeters, 1) + ' متر — ' + formatMoney(edgeCost));
    setText('[data-letter-build-cost]', formatMeasure(perimeterMeters, 1) + ' متر — ' + formatMoney(buildCost));
    setText('[data-letter-plexi-cut-cost]', usesMetalFace ? 'محاسبه نمی‌شود' : formatMeasure(laserPerimeterMeters, 1) + ' متر برش — ' + formatMoney(plexiCutCost));
    setText('[data-letter-pvc-cost]', formatMeasure(consumedSquareMeters, 3) + ' مترمربع — ' + formatMoney(pvcCost));
    setText('[data-letter-pvc-cut-cost]', formatMeasure(perimeterMeters, 1) + ' متر — ' + formatMoney(pvcCutCost));
    setText('[data-letter-glue-cost]', formatMeasure(perimeterMeters, 1) + ' متر — ' + formatMoney(glueCost));
    setText('[data-letter-double-labor-cost]', doublePerimeterMeters > 0 ? formatMeasure(doublePerimeterMeters, 1) + ' متر — ' + formatMoney(doubleLaborCost) : 'محاسبه نمی‌شود');
    setText('[data-letter-smd-label]', smdType !== 'none' ? 'هزینه ' + smdLabels[smdType] + ' با نصب' : 'هزینه SMD با نصب');
    setText('[data-letter-led-cost]', smdType === 'roll'
      ? formatMeasure(smdInstalledLengthMeters, 2) + ' متر نصب؛ ' + formatMeasure(smdPurchaseLengthMeters, 2) + ' متر با پرت؛ ' + smdLayout.rollCount.toLocaleString('fa-IR') + ' رول — ' + formatMoney(smdCost)
      : (smdType !== 'none' ? smdCount.toLocaleString('fa-IR') + ' واحد — ' + formatMoney(smdCost) : 'محاسبه نشده'));
    setText('[data-letter-installation-cost]', installationMode === 'perimeter' ? formatMeasure(perimeterMeters, 1) + ' متر — ' + formatMoney(installation) : 'مبلغ کلی — ' + formatMoney(installation));
    setText('[data-letter-travel-cost]', formatMoney(travel));
    setText('[data-letter-transformer-cost]', !useTransformer
      ? 'استفاده نمی‌شود'
      : (transformerCount > 0 ? transformerPlanText(transformerPlan) + ' — ' + formatMoney(transformer) : 'بدون SMD'));
    setText('[data-letter-wire-supplies]', formatMoney(wireSupplies));
    setText('[data-letter-extras]', formatMoney(extras));
    setText('[data-letter-base]', formatMoney(base));
    setText('[data-letter-profit]', formatMoney(profit) + ' (' + formatMeasure(profitPercent) + '٪)');
    setText('[data-letter-insurance-tax]', insuranceTaxPercent > 0 ? formatMoney(insuranceTax) + ' (' + formatMeasure(insuranceTaxPercent) + '٪)' : 'محاسبه نشده');
    setText('[data-letter-final]', formatMoney(finalPrice));
    setText('[data-letter-unit-price]', perimeterMeters > 0 ? formatMoney(Math.round(finalPrice / perimeterMeters)) + ' به‌ازای هر متر' : 'محیط قابل محاسبه نیست');
    drawSmdPreview(state, smdLayout, smdType);
  }

  function showAnalysis(state) {
    var materialUsage = {};
    state.consumedAreaMm2 = state.sheets.reduce(function (sum, sheet) {
      updateSheetConsumption(state, sheet);
      var materialKey = sheet.materialKey || sheet.materialColor || '#000000';
      if (!materialUsage[materialKey]) {
        materialUsage[materialKey] = {
          key: materialKey,
          color: sheet.materialColor || '#000000',
          label: sheet.materialLabel || plexiColorName(sheet.materialColor || '#000000'),
          sheets: 0,
          parts: 0,
          areaMm2: 0,
          consumedAreaMm2: 0
        };
      }
      materialUsage[materialKey].sheets += 1;
      materialUsage[materialKey].parts += sheet.placements.length;
      materialUsage[materialKey].areaMm2 += sheet.placements.reduce(function (area, placement) { return area + placement.item.areaMm2; }, 0);
      materialUsage[materialKey].consumedAreaMm2 += sheet.consumptionAreaMm2;
      return sum + sheet.consumptionAreaMm2;
    }, 0);
    state.materialUsage = Object.keys(materialUsage).map(function (key) { return materialUsage[key]; });
    state.doubleConsumedAreaMm2 = 0;
    state.placedMaterialAreaMm2 = state.sheets.reduce(function (total, sheet) {
      return total + sheet.placements.reduce(function (area, placement) { return area + placement.item.areaMm2; }, 0);
    }, 0);
    var wasteArea = Math.max(0, state.consumedAreaMm2 - state.placedMaterialAreaMm2);
    var utilization = state.consumedAreaMm2 > 0 ? (state.placedMaterialAreaMm2 / state.consumedAreaMm2) * 100 : 0;
    setText('[data-letter-design-size]', formatMeasure(state.designWidthMm) + ' × ' + formatMeasure(state.designHeightMm) + ' میلی‌متر');
    setText('[data-letter-parts]', state.components.length.toLocaleString('fa-IR') + ' قطعه برش؛ ' + state.doubleParts.toLocaleString('fa-IR') + ' قطعه دوبل؛ ' + (state.lightingComponents || []).length.toLocaleString('fa-IR') + ' قطعه مستقل روشنایی' + (state.unplacedItems.length ? '؛ ' + state.unplacedItems.length.toLocaleString('fa-IR') + ' قطعه جا‌نشده' : ''));
    setText('[data-letter-area]', formatMeasure(state.areaMm2 / 1000000, 3) + ' مترمربع');
    setText('[data-letter-lighting-area]', formatMeasure((state.lightingAreaMm2 || state.areaMm2) / 1000000, 3) + ' مترمربع');
    setText('[data-letter-perimeter]', formatMeasure(roundUpToOneDecimal(state.perimeterMm / 1000), 1) + ' متر');
    setText('[data-letter-special-paths]', formatMeasure(roundUpToOneDecimal(state.doublePerimeterMm / 1000), 1) + ' متر دوبل؛ ' + formatMeasure(roundUpToOneDecimal(state.pinPerimeterMm / 1000), 1) + ' متر پین‌کات');
    setText('[data-letter-sheets]', state.sheets.length.toLocaleString('fa-IR') + ' ورق ' + formatMeasure(state.sheetWidthMm, 0) + ' × ' + formatMeasure(state.sheetHeightMm, 0));
    setText('[data-letter-waste]', formatMeasure(state.consumedAreaMm2 / 1000000, 3) + ' مترمربع مصرف مستطیلی؛ ' + formatMeasure(wasteArea / 1000000, 3) + ' مترمربع پرت داخل آن');
    setText('[data-letter-utilization]', 'بهره‌وری ' + formatMeasure(utilization, 1) + '٪');
    setText('[data-letter-accuracy]', 'تفکیک ' + state.materialUsage.length.toLocaleString('fa-IR') + ' رنگ/لایه پلکسی؛ ' + state.layoutTrials.toLocaleString('fa-IR') + ' چیدمان آزمایشی؛ دقت تقریبی: ' + formatMeasure(state.stepMm, 1) + ' میلی‌متر');
    var materials = form.querySelector('[data-letter-materials]');
    if (materials) {
      materials.innerHTML = '<strong>مصرف پلکسی به تفکیک رنگ و لایه</strong>' + state.materialUsage.map(function (usage) {
        return '<div><i style="--material-color:' + usage.color + '"></i><span><b>' + usage.label + '</b><small>'
          + usage.parts.toLocaleString('fa-IR') + ' قطعه؛ ' + usage.sheets.toLocaleString('fa-IR') + ' ورق؛ '
          + formatMeasure(usage.consumedAreaMm2 / 1000000, 3) + ' مترمربع مصرف</small></span></div>';
      }).join('');
      materials.hidden = !state.materialUsage.length;
    }
    drawUnplacedItems(state);
    drawPreview(state);
    form.querySelector('[data-letter-analysis]').hidden = false;
    calculateCosts();
  }

  function letterRateSnapshot() {
    var moneyNames = [
      'plexi_sqm_rate', 'metal_sheet_07_sqm_rate', 'edge_swedish_material_rate', 'edge_swedish_labor_rate',
      'edge_plastic_material_rate', 'edge_plastic_labor_rate', 'edge_channelium_material_rate',
      'edge_channelium_labor_rate', 'edge_metal_material_rate', 'edge_metal_labor_rate',
      'metal_powder_coating_rate', 'double_layer_labor_rate',
      'pvc_rate', 'plexi_cut_rate', 'pvc_cut_rate', 'glue_rate',
      'smd_block_rate', 'smd_lens_rate', 'smd_roll_rate',
      'transformer_60_rate', 'transformer_100_rate', 'transformer_120_rate',
      'transformer_200_rate', 'transformer_300_rate', 'transformer_400_rate'
    ];
    var decimalNames = [
      'sheet_width_mm', 'sheet_height_mm', 'smd_block_units_per_square_meter',
      'smd_lens_units_per_square_meter',
      'smd_block_width_mm', 'smd_block_height_mm', 'smd_block_led_count',
      'smd_lens_width_mm', 'smd_lens_height_mm', 'smd_lens_led_count',
      'smd_roll_strip_width_mm', 'smd_roll_watts_per_meter', 'smd_roll_length_m',
      'smd_roll_cut_interval_mm', 'smd_roll_waste_percent', 'smd_roll_transformer_reserve_percent',
      'smd_roll_wall_clearance_mm', 'smd_roll_row_spacing_mm',
      'transformer_60_capacity', 'transformer_100_capacity', 'transformer_120_capacity',
      'transformer_200_capacity', 'transformer_300_capacity', 'transformer_400_capacity',
      'cut_gap_mm', 'sheet_margin_mm'
    ];
    var rates = {};
    moneyNames.forEach(function (name) { rates[name] = money(rateField(name).value); });
    decimalNames.forEach(function (name) { rates[name] = decimal(rateField(name).value); });
    return rates;
  }

  function captureLayoutPreviews() {
    var previews = [];
    var canvases = form.querySelectorAll('[data-letter-sheet-preview] canvas');
    Array.prototype.slice.call(canvases, 0, 24).forEach(function (canvas) {
      if (!canvas.width || !canvas.height) return;
      var scale = Math.min(1, 180 / canvas.width, 250 / canvas.height);
      var thumbnail = document.createElement('canvas');
      thumbnail.width = Math.max(1, Math.round(canvas.width * scale));
      thumbnail.height = Math.max(1, Math.round(canvas.height * scale));
      var context = thumbnail.getContext('2d');
      context.fillStyle = '#ffffff';
      context.fillRect(0, 0, thumbnail.width, thumbnail.height);
      context.imageSmoothingEnabled = true;
      context.imageSmoothingQuality = 'high';
      context.drawImage(canvas, 0, 0, thumbnail.width, thumbnail.height);
      previews.push(thumbnail.toDataURL('image/jpeg', 0.76));
    });
    return previews;
  }

  function captureSmdPreview() {
    var canvas = form.querySelector('[data-letter-smd-canvas] canvas');
    if (!canvas || !canvas.width || !canvas.height) return '';
    var scale = Math.min(1, 900 / canvas.width, 620 / canvas.height);
    var thumbnail = document.createElement('canvas');
    thumbnail.width = Math.max(1, Math.round(canvas.width * scale));
    thumbnail.height = Math.max(1, Math.round(canvas.height * scale));
    var context = thumbnail.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, thumbnail.width, thumbnail.height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(canvas, 0, 0, thumbnail.width, thumbnail.height);
    return thumbnail.toDataURL('image/jpeg', 0.82);
  }

  function buildEstimateSnapshot() {
    if (!analysisState || !currentCalculation || !cachedText) {
      throw new Error('ابتدا فایل را تحلیل کنید تا محاسبه کامل شود.');
    }
    var sourceFile = droppedFile || (field('letter_svg').files && field('letter_svg').files[0]);
    return {
      version: 2,
      calculator_type: 'letters',
      source_file: sourceFile ? sourceFile.name : 'طرح.svg',
      svg: cachedText,
      edge_type: field('edge_type').value,
      smd_type: field('smd_type').value,
      installation_mode: field('installation_mode').value === 'perimeter' ? 'perimeter' : 'fixed',
      use_transformer: field('use_transformer').checked ? 1 : 0,
      allow_rotation: 1,
      include_pvc: 1,
      include_metal_sheet_07: field('edge_type').value === 'metal' ? 1 : 0,
      consumption_modes: analysisState.sheets.map(function (sheet) { return sheet.consumptionMode === 'tight' ? 'tight' : 'full'; }),
      materials: (analysisState.materialUsage || []).map(function (usage) {
        return {
          key: usage.key,
          color: usage.color,
          label: usage.label,
          sheets: usage.sheets,
          parts: usage.parts,
          area_mm2: usage.areaMm2,
          consumed_area_mm2: usage.consumedAreaMm2
        };
      }),
      layout_previews: captureLayoutPreviews(),
      smd_preview: captureSmdPreview(),
      inputs: {
        design_width_mm: decimal(field('design_width_mm').value),
        design_height_mm: decimal(field('design_height_mm').value),
        installation: money(field('installation').value),
        travel: money(field('travel').value),
        wire_supplies: money(field('wire_supplies').value),
        profit_percent: decimal(field('profit_percent').value),
        insurance_tax_percent: decimal(field('insurance_tax_percent').value),
        use_transformer: field('use_transformer').checked ? 1 : 0,
        layout_trials: Number(field('layout_trials').value) || 10
      },
      rates: letterRateSnapshot(),
      analysis: {
        area_mm2: analysisState.areaMm2,
        lighting_area_mm2: analysisState.lightingAreaMm2 || analysisState.areaMm2,
        consumed_area_mm2: analysisState.consumedAreaMm2,
        perimeter_mm: analysisState.perimeterMm,
        rounded_perimeter_m: currentCalculation.rounded_perimeter_m,
        primary_perimeter_mm: analysisState.primaryPerimeterMm,
        double_perimeter_mm: analysisState.doublePerimeterMm,
        pin_perimeter_mm: analysisState.pinPerimeterMm,
        laser_perimeter_mm: analysisState.laserPerimeterMm,
        double_area_mm2: analysisState.doubleAreaMm2,
        double_consumed_area_mm2: analysisState.doubleConsumedAreaMm2,
        lighting_parts: (analysisState.lightingComponents || []).length,
        parts: analysisState.components.length,
        sheets: analysisState.sheets.length,
        unplaced_parts: analysisState.unplacedItems.length,
        design_width_mm: analysisState.designWidthMm,
        design_height_mm: analysisState.designHeightMm
      },
      breakdown: currentCalculation
    };
  }

  function estimateRequest(action, fields) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', form.dataset.estimatesNonce || '');
    Object.keys(fields || {}).forEach(function (key) { body.append(key, fields[key]); });
    return fetch(form.dataset.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: body
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) {
          throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'عملیات انجام نشد.');
        }
        return payload.data;
      });
    });
  }

  function renderEstimateRecords(records, listing) {
    var list = document.querySelector('[data-letter-estimate-list]');
    var count = document.querySelector('[data-letter-estimate-count]');
    var section = document.querySelector('[data-letter-estimates]');
    var pageLabel = document.querySelector('[data-letter-estimate-page-label]');
    var previousButton = document.querySelector('[data-letter-estimate-page="prev"]');
    var nextButton = document.querySelector('[data-letter-estimate-page="next"]');
    if (!list) return;
    list.innerHTML = '';
    var total = listing ? Number(listing.total || 0) : Number(records.length);
    var currentPage = listing ? Math.max(1, Number(listing.page || 1)) : 1;
    var totalPages = listing ? Math.max(1, Number(listing.pages || 1)) : 1;
    if (count) count.textContent = total.toLocaleString('fa-IR') + ' مورد';
    if (section) {
      section.dataset.currentPage = currentPage;
      section.dataset.totalPages = totalPages;
    }
    if (pageLabel) pageLabel.textContent = 'صفحه ' + currentPage.toLocaleString('fa-IR') + ' از ' + totalPages.toLocaleString('fa-IR');
    if (previousButton) previousButton.disabled = currentPage <= 1;
    if (nextButton) nextButton.disabled = currentPage >= totalPages;
    if (!records.length) {
      var empty = document.createElement('p');
      empty.className = 'manager-pricing-estimates__empty';
      var search = document.querySelector('[data-letter-estimate-search]');
      empty.textContent = search && search.value.trim() ? 'برآوردی با این نام پیدا نشد.' : 'هنوز محاسبه‌ای ذخیره نشده است.';
      list.appendChild(empty);
      return;
    }
    records.forEach(function (record) {
      var article = document.createElement('article');
      article.dataset.estimateId = record.id;
      var info = document.createElement('div');
      var title = document.createElement('strong');
      title.textContent = record.project_name;
      var date = document.createElement('small');
      date.textContent = 'آخرین تغییر: ' + record.modified;
      info.appendChild(title);
      info.appendChild(date);
      var price = document.createElement('b');
      price.textContent = Number(record.final_price || 0).toLocaleString('fa-IR') + ' ریال';
      var priceDetails = document.createElement('small');
      priceDetails.textContent = Number(record.perimeter_m || 0).toLocaleString('fa-IR', {maximumFractionDigits: 1}) + ' متر · ' + Number(record.unit_price || 0).toLocaleString('fa-IR') + ' ریال/متر';
      price.appendChild(priceDetails);
      var actions = document.createElement('div');
      actions.className = 'manager-pricing-estimates__actions';
      var load = document.createElement('button');
      load.type = 'button';
      load.dataset.letterEstimateLoad = record.id;
      load.textContent = 'بازکردن و ویرایش';
      var print = document.createElement('button');
      print.type = 'button';
      print.dataset.letterEstimatePrintSaved = record.id;
      print.textContent = 'چاپ / PDF';
      actions.appendChild(load);
      actions.appendChild(print);
      article.appendChild(info);
      article.appendChild(price);
      article.appendChild(actions);
      list.appendChild(article);
    });
  }

  function loadEstimateList(requestedPage) {
    var section = document.querySelector('[data-letter-estimates]');
    var search = document.querySelector('[data-letter-estimate-search]');
    var status = document.querySelector('[data-letter-estimate-list-status]');
    var page = Math.max(1, Number(requestedPage || (section ? section.dataset.currentPage : 1)));
    if (status) {
      status.textContent = 'در حال به‌روزرسانی فهرست…';
      status.className = 'manager-pricing-estimates__status is-saving';
    }
    return estimateRequest('zigurat_list_pricing_estimates', {
      page: page,
      search: search ? search.value.trim() : ''
    }).then(function (data) {
      renderEstimateRecords(data.records || [], data);
      if (status) {
        status.textContent = '';
        status.className = 'manager-pricing-estimates__status';
      }
      return data;
    }).catch(function (error) {
      if (status) {
        status.textContent = error.message;
        status.className = 'manager-pricing-estimates__status is-error';
      }
      throw error;
    });
  }

  function setEstimateStatus(message, state) {
    var status = form.querySelector('[data-letter-estimate-status]');
    if (!status) return;
    status.textContent = message || '';
    status.className = state ? 'is-' + state : '';
  }

  function restoreEstimate(payload) {
    var snapshot = payload.snapshot || {};
    var inputs = snapshot.inputs || {};
    var rates = snapshot.rates || {};
    var savedBreakdown = snapshot.breakdown || {};
    Object.keys(rates).forEach(function (name) {
      var input = rateField(name);
      if (input) input.value = rates[name];
    });
    Object.keys(inputs).forEach(function (name) {
      var input = field(name);
      if (input) input.value = inputs[name];
    });
    field('insurance_tax_percent').value = inputs.insurance_tax_percent !== undefined
      ? inputs.insurance_tax_percent
      : (savedBreakdown.insurance_tax_percent || 0);
    field('use_transformer').checked = inputs.use_transformer !== undefined
      ? Number(inputs.use_transformer) !== 0
      : (snapshot.use_transformer !== undefined ? Number(snapshot.use_transformer) !== 0 : true);
    field('layout_trials').value = [5, 10, 20, 30].indexOf(Number(inputs.layout_trials)) !== -1 ? String(inputs.layout_trials) : '10';
    field('edge_type').value = snapshot.edge_type || 'swedish';
    field('smd_type').value = snapshot.smd_type || 'none';
    field('installation_mode').value = snapshot.installation_mode === 'perimeter' ? 'perimeter' : 'fixed';
    updateInstallationModeUi();
    field('estimate_project_name').value = payload.project_name || '';
    field('estimate_id').value = payload.id || 0;
    rateField('active_edge_type').value = field('edge_type').value;
    if (field('smd_type').value !== 'none') rateField('active_smd_type').value = field('smd_type').value;
    showSelectedRatePanel('edge', rateField('active_edge_type').value);
    showSelectedRatePanel('smd', rateField('active_smd_type').value);
    updateSelectedRateSummaries();
    var mode = form.querySelector('[data-letter-estimate-mode]');
    var newButton = form.querySelector('[data-letter-estimate-new]');
    if (mode) mode.textContent = 'در حال ویرایش برآورد ذخیره‌شده شماره ' + Number(payload.id).toLocaleString('fa-IR') + ' هستید.';
    if (newButton) newButton.hidden = false;

    if (!snapshot.svg) throw new Error('فایل طرح ذخیره‌شده در این رکورد موجود نیست.');
    var file = new File([snapshot.svg], snapshot.source_file || 'طرح.svg', {type: 'image/svg+xml'});
    droppedFile = file;
    try {
      var transfer = new DataTransfer();
      transfer.items.add(file);
      field('letter_svg').files = transfer.files;
    } catch (error) {}
    setBusy(true);
    return handleSelectedFile(file).then(function () {
      field('design_width_mm').value = inputs.design_width_mm || '';
      field('design_height_mm').value = inputs.design_height_mm || '';
      return analyze();
    }).then(function () {
      if (Array.isArray(snapshot.consumption_modes)) {
        analysisState.sheets.forEach(function (sheet, index) {
          sheet.consumptionMode = snapshot.consumption_modes[index] === 'tight' ? 'tight' : 'full';
        });
        showAnalysis(analysisState);
      }
      setEstimateStatus('محاسبه ذخیره‌شده با نرخ‌های همان زمان باز شد.', 'success');
      form.scrollIntoView({behavior: 'smooth', block: 'start'});
    }).finally(function () { setBusy(false); });
  }

  function escapeEstimateHtml(value) {
    return String(value === undefined || value === null ? '' : value).replace(/[&<>'"]/g, function (character) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character];
    });
  }

  function estimatePrintHtml(projectName, snapshot) {
    var analysis = snapshot.analysis || {};
    var breakdown = snapshot.breakdown || {};
    var inputs = snapshot.inputs || {};
    var rates = snapshot.rates || {};
    var edgeName = edgeLabels[snapshot.edge_type] || '—';
    var smdName = smdLabels[snapshot.smd_type] || 'بدون SMD';
    var edgeKey = snapshot.edge_type || 'swedish';
    var smdKey = snapshot.smd_type || 'none';
    var useTransformer = inputs.use_transformer !== undefined
      ? Number(inputs.use_transformer) !== 0
      : (snapshot.use_transformer !== undefined ? Number(snapshot.use_transformer) !== 0 : true);
    var transformerPlan;
    if (!useTransformer) {
      transformerPlan = {items: [], count: 0, capacity: 0, cost: 0};
    } else if (smdKey === 'roll') {
      transformerPlan = recommendTransformersByWatts(
        Number(breakdown.smd_power_watts || 0),
        Object.prototype.hasOwnProperty.call(rates, 'smd_roll_transformer_reserve_percent') ? Number(rates.smd_roll_transformer_reserve_percent) : 20,
        rates
      );
    } else if (snapshot.transformer_type && ['200', '300', '400'].indexOf(String(snapshot.transformer_type)) !== -1) {
      var legacyWatts = Number(snapshot.transformer_type);
      var legacyCount = Number(breakdown.transformer_count || 0);
      var legacyCapacity = Number(rates['transformer_' + legacyWatts + '_capacity'] || legacyWatts);
      var legacyRate = Number(rates['transformer_' + legacyWatts + '_rate'] || 0);
      transformerPlan = {
        items: legacyCount ? [{watts: legacyWatts, capacity: legacyCapacity, rate: legacyRate, count: legacyCount, cost: Number(breakdown.transformer || 0)}] : [],
        count: legacyCount,
        capacity: legacyCount * legacyCapacity,
        cost: Number(breakdown.transformer || 0)
      };
    } else {
      transformerPlan = recommendTransformers(Number(breakdown.smd_count || 0), rates);
    }
    var installationMode = snapshot.installation_mode === 'perimeter' ? 'perimeter' : 'fixed';
    var installationBasis = installationMode === 'perimeter' ? formatMeasure(analysis.rounded_perimeter_m || 0, 1) + ' متر محیط' : 'مبلغ کلی';
    var installationRate = installationMode === 'perimeter' ? inputs.installation : null;
    var doublePerimeterMeters = Number(breakdown.double_perimeter_m || ((analysis.double_perimeter_mm || 0) / 1000));
    var pinPerimeterMeters = Number(breakdown.pin_perimeter_m || ((analysis.pin_perimeter_mm || 0) / 1000));
    var laserPerimeterMeters = Number(breakdown.laser_perimeter_m || ((analysis.laser_perimeter_mm || 0) / 1000) || analysis.rounded_perimeter_m || 0);
    var usesMetalFace = edgeKey === 'metal';
    var smdDensityCount = Number(breakdown.smd_density_count || breakdown.smd_count || 0);
    var smdMinimumNote = Number(breakdown.smd_count || 0) > smdDensityCount
      ? '؛ با اعمال حداقل یک واحد برای هر قطعه مستقل'
      : '';
    var smdModuleDescription = smdKey === 'block' || smdKey === 'lens'
      ? '؛ ابعاد بلوک ' + (smdKey === 'lens' ? 'لنزدار ' : '') + formatMeasure(Number(rates['smd_' + smdKey + '_width_mm'] || 75), 1) + ' × ' + formatMeasure(Number(rates['smd_' + smdKey + '_height_mm'] || 15), 1) + ' میلی‌متر، ' + Number(rates['smd_' + smdKey + '_led_count'] || 3).toLocaleString('fa-IR') + ' چیپ'
      : '';
    var smdBasis = 'استفاده نشده';
    if (smdKey === 'roll') {
      smdBasis = formatMeasure(Number(breakdown.smd_length_m || 0), 2) + ' متر نصب؛ '
        + formatMeasure(Number(breakdown.smd_purchase_length_m || 0), 2) + ' متر با پرت؛ '
        + Number(breakdown.smd_roll_count || 0).toLocaleString('fa-IR') + ' رول؛ '
        + formatMeasure(Number(breakdown.smd_power_watts || 0), 1) + ' وات'
        + (Number(breakdown.smd_max_lane_count || 0) > 1 ? '؛ حداکثر ' + Number(breakdown.smd_max_lane_count).toLocaleString('fa-IR') + ' ردیف در قسمت‌های پهن' : '');
    } else if (snapshot.smd_type !== 'none') {
      smdBasis = Number(breakdown.smd_count || 0).toLocaleString('fa-IR') + ' واحد؛ سطح مبنا '
        + formatMeasure(Number(analysis.lighting_area_mm2 || analysis.area_mm2 || 0) / 1000000, 3) + ' مترمربع'
        + smdModuleDescription + smdMinimumNote;
    }
    var faceRows = usesMetalFace ? [
      ['ورق فلزی ۰٫۷', formatMeasure((analysis.consumed_area_mm2 || 0) / 1000000, 3) + ' مترمربع', rates.metal_sheet_07_sqm_rate, breakdown.metal_sheet_07],
      ['رنگ کوره‌ای', formatMeasure((analysis.area_mm2 || 0) / 1000000, 3) + ' مترمربع', rates.metal_powder_coating_rate, breakdown.powder_coating]
    ] : [
      ['مصرف پلکسی', formatMeasure((analysis.consumed_area_mm2 || 0) / 1000000, 3) + ' مترمربع', rates.plexi_sqm_rate, breakdown.plexi],
      ['برش پلکسی', formatMeasure(laserPerimeterMeters, 1) + ' متر؛ شامل برش زیر و رو و پین‌کات', rates.plexi_cut_rate, breakdown.plexi_cut]
    ];
    var rows = faceRows.concat([
      ['قیمت ' + edgeName, formatMeasure(analysis.rounded_perimeter_m || 0, 1) + ' متر', rates['edge_' + edgeKey + '_material_rate'], breakdown.edge],
      ['اجرت ساخت ' + edgeName, formatMeasure(analysis.rounded_perimeter_m || 0, 1) + ' متر', rates['edge_' + edgeKey + '_labor_rate'], breakdown.edge_labor],
    ]).concat(doublePerimeterMeters > 0 ? [
      ['اجرت دوبل', formatMeasure(doublePerimeterMeters, 1) + ' متر مسیر آبی', rates.double_layer_labor_rate, breakdown.double_labor]
    ] : []).concat([
      ['PVC', formatMeasure((analysis.consumed_area_mm2 || 0) / 1000000, 3) + ' مترمربع', rates.pvc_rate, breakdown.pvc],
      ['برش PVC', formatMeasure(analysis.rounded_perimeter_m || 0, 1) + ' متر', rates.pvc_cut_rate, breakdown.pvc_cut],
      ['چسب', formatMeasure(analysis.rounded_perimeter_m || 0, 1) + ' متر', rates.glue_rate, breakdown.glue],
      [smdName, smdBasis, smdKey !== 'none' ? rates['smd_' + smdKey + '_rate'] : 0, breakdown.smd],
      ['ترانس پیشنهادی', useTransformer ? transformerPlanText(transformerPlan) : 'استفاده نمی‌شود', null, breakdown.transformer],
      ['نصب', installationBasis, installationRate, breakdown.installation], ['ایاب و ذهاب', '', null, breakdown.travel],
      ['سیم و لوازم مصرفی', '', null, breakdown.wire_supplies],
      ['جمع هزینه‌های جانبی', '', null, Number(breakdown.installation || 0) + Number(breakdown.travel || 0) + Number(breakdown.wire_supplies || 0)],
      ['جمع هزینه پایه', '', null, breakdown.base],
      ['سود (' + formatMeasure(inputs.profit_percent || 0, 2) + '٪)', '', null, breakdown.profit],
      ['بیمه و مالیات (' + formatMeasure(inputs.insurance_tax_percent || breakdown.insurance_tax_percent || 0, 2) + '٪)', '', null, breakdown.insurance_tax]
    ]);
    var rowsHtml = rows.map(function (row) {
      var rate = row[2] === null ? '—' : formatMoney(Number(row[2] || 0));
      return '<tr><td>' + escapeEstimateHtml(row[0]) + '</td><td>' + escapeEstimateHtml(row[1]) + '</td><td>' + escapeEstimateHtml(rate) + '</td><td>' + escapeEstimateHtml(formatMoney(Number(row[3] || 0))) + '</td></tr>';
    }).join('');
    var svgPreview = snapshot.svg ? '<img class="design" src="data:image/svg+xml;charset=utf-8,' + encodeURIComponent(snapshot.svg) + '" alt="طرح پروژه">' : '';
    var layoutPreviews = Array.isArray(snapshot.layout_previews) ? snapshot.layout_previews : [];
    var materialRows = Array.isArray(snapshot.materials) ? snapshot.materials : [];
    var materialsHtml = materialRows.length ? '<section class="material-section"><h2>مصرف پلکسی به تفکیک رنگ و لایه</h2><div class="materials">' + materialRows.map(function (material) {
      return '<div><i style="background:' + escapeEstimateHtml(material.color || '#777777') + '"></i><span><b>' + escapeEstimateHtml(material.label || material.color || 'پلکسی') + '</b><small>'
        + escapeEstimateHtml(Number(material.parts || 0).toLocaleString('fa-IR') + ' قطعه؛ ' + Number(material.sheets || 0).toLocaleString('fa-IR') + ' ورق؛ ' + formatMeasure(Number(material.consumed_area_mm2 || 0) / 1000000, 3) + ' مترمربع مصرف')
        + '</small></span></div>';
    }).join('') + '</div></section>' : '';
    var layoutsHtml = layoutPreviews.length ? '<section class="layout-section"><h2>پیش‌نمایش چیدمان ورق‌ها</h2><div class="layouts">' + layoutPreviews.map(function (source, index) {
      return '<figure><img src="' + escapeEstimateHtml(source) + '" alt="چیدمان ورق ' + (index + 1) + '"><figcaption>ورق ' + Number(index + 1).toLocaleString('fa-IR') + '</figcaption></figure>';
    }).join('') + '</div></section>' : '';
    var smdPreviewSummary = smdKey === 'roll'
      ? formatMeasure(Number(breakdown.smd_length_m || 0), 2) + ' متر مسیر نصب روی ' + Number(analysis.lighting_parts || 0).toLocaleString('fa-IR') + ' قطعه مستقل روشنایی'
      : Number(breakdown.smd_count || 0).toLocaleString('fa-IR') + ' واحد روی ' + Number(analysis.lighting_parts || 0).toLocaleString('fa-IR') + ' قطعه مستقل روشنایی';
    var smdPreviewHtml = snapshot.smd_preview && snapshot.smd_type !== 'none'
      ? '<section class="smd-section"><h2>چیدمان تقریبی SMD روی حروف</h2><img src="' + escapeEstimateHtml(snapshot.smd_preview) + '" alt="چیدمان SMD"><p>'
        + escapeEstimateHtml(smdPreviewSummary)
        + '</p></section>'
      : '';
    var unplacedNotice = Number(analysis.unplaced_parts || 0) > 0
      ? '<div class="unplaced-notice">' + escapeEstimateHtml(Number(analysis.unplaced_parts).toLocaleString('fa-IR') + ' قطعه به‌دلیل بزرگی در ورق جا نشدند و در مصرف ورق منظور نشده‌اند.') + '</div>'
      : '';
    var safeTitle = escapeEstimateHtml(projectName || 'برآورد قیمت حروف');
    return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>برآورد قیمت - ' + safeTitle + '</title><style>'
      + '@page{size:A4 portrait;margin:0}*{box-sizing:border-box}html,body{margin:0;padding:0}body{padding:12mm;font-family:Tahoma,Arial,sans-serif;color:#171717;direction:rtl}header{display:flex;align-items:center;justify-content:space-between;border-bottom:3px solid #b78a2d;padding-bottom:10px;margin-bottom:15px}h1{font-size:22px;margin:0}h2{margin:0 0 8px;font-size:14px}header span{color:#6b5a32}.meta{display:grid;grid-template-columns:repeat(2,1fr);border:1px solid #bbb;margin-bottom:14px}.meta div{padding:8px 10px;border-bottom:1px solid #ddd}.meta div:nth-child(odd){border-left:1px solid #ddd}.design{display:block;max-width:100%;max-height:145px;margin:10px auto 14px}.material-section,.layout-section,.smd-section{margin:10px 0 14px;padding:9px;border:1px solid #cfc7b7;background:#faf8f2;break-inside:avoid}.smd-section{border-color:#aec5d5;background:#f3f8fb;text-align:center}.smd-section img{display:block;width:auto;max-width:100%;max-height:90mm;margin:0 auto 6px;object-fit:contain}.smd-section p{margin:4px 0 0;color:#31536c;font-size:10px;font-weight:bold}.materials{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px}.materials>div{display:flex;gap:7px;align-items:center;padding:6px;background:#fff;border:1px solid #ddd}.materials i{width:18px;height:18px;border-radius:4px;border:1px solid #999;flex:none}.materials span{display:flex;flex-direction:column}.materials small{font-size:9px;color:#666}.layouts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px}.layouts figure{margin:0;padding:5px;border:1px solid #d8d1c3;background:#fff;text-align:center;break-inside:avoid}.layouts img{display:block;width:auto;max-width:100%;height:34mm;margin:auto;object-fit:contain}.layouts figcaption{margin-top:4px;color:#665b46;font-size:9px;font-weight:bold}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #999;padding:6px 8px;text-align:right}th{background:#eee}td:last-child{text-align:left}.final{display:flex;justify-content:space-between;margin-top:12px;padding:12px 14px;background:#222;color:#fff;font-size:19px;font-weight:bold}.final strong{display:grid;text-align:left}.final small{margin-top:4px;color:#e8d8a7;font-size:11px}.unplaced-notice{margin:10px 0;padding:8px;border:1px solid #d67b70;background:#fff4f2;color:#8a271d;font-size:10px;font-weight:bold}.note{margin-top:10px;color:#666;font-size:10px}@media print{body{padding:12mm}.no-print{display:none!important}}</style></head><body>'
      + '<header><h1>برآورد قیمت ساخت حروف</h1><span>زیگورات</span></header>'
      + '<section class="meta"><div><b>نام پروژه:</b> ' + safeTitle + '</div><div><b>فایل طرح:</b> ' + escapeEstimateHtml(snapshot.source_file || '—') + '</div><div><b>ابعاد:</b> ' + escapeEstimateHtml(formatMeasure(analysis.design_width_mm || 0, 2) + ' × ' + formatMeasure(analysis.design_height_mm || 0, 2) + ' میلی‌متر') + '</div><div><b>مساحت واقعی رویه:</b> ' + escapeEstimateHtml(formatMeasure((analysis.area_mm2 || 0) / 1000000, 3) + ' مترمربع') + '</div><div><b>محیط محاسباتی:</b> ' + escapeEstimateHtml(formatMeasure(analysis.rounded_perimeter_m || 0, 1) + ' متر') + '</div><div><b>نوع SMD:</b> ' + escapeEstimateHtml(smdName) + '</div></section>'
      + svgPreview + smdPreviewHtml + materialsHtml + layoutsHtml + unplacedNotice + '<table><thead><tr><th>شرح</th><th>مبنای محاسبه</th><th>نرخ واحد</th><th>هزینه</th></tr></thead><tbody>' + rowsHtml + '</tbody></table>'
      + '<div class="final"><span>قیمت نهایی</span><strong>' + escapeEstimateHtml(formatMoney(Number(breakdown.final || 0))) + '<small>' + escapeEstimateHtml(Number(analysis.rounded_perimeter_m || 0) > 0 ? formatMoney(Math.round(Number(breakdown.final || 0) / Number(analysis.rounded_perimeter_m))) + ' به‌ازای هر متر' : 'محیط قابل محاسبه نیست') + '</small></strong></div><p class="note">این گزارش براساس نرخ‌ها و اطلاعات ذخیره‌شده همین برآورد تهیه شده است.</p>'
      + '<script>window.addEventListener("load",function(){setTimeout(function(){window.print()},300)})<\/script></body></html>';
  }

  function openEstimatePrint(projectName, snapshot, printWindow) {
    var popup = printWindow || window.open('', '_blank');
    if (!popup) throw new Error('مرورگر پنجره چاپ را مسدود کرده است. اجازه Pop-up را فعال کنید.');
    popup.document.open();
    popup.document.write(estimatePrintHtml(projectName, snapshot));
    popup.document.close();
  }

  function analyze() {
    clearError();
    var upload = field('letter_svg');
    var file = droppedFile || (upload.files && upload.files[0]);
    var designWidth = decimal(field('design_width_mm').value);
    var designHeight = decimal(field('design_height_mm').value);
    var sheetWidth = decimal(rateField('sheet_width_mm').value);
    var sheetHeight = decimal(rateField('sheet_height_mm').value);
    var gap = decimal(rateField('cut_gap_mm').value);
    var margin = decimal(rateField('sheet_margin_mm').value);
    var layoutTrials = [5, 10, 20, 30].indexOf(Number(field('layout_trials').value)) !== -1 ? Number(field('layout_trials').value) : 10;
    if (!file) return Promise.reject(new Error('ابتدا فایل SVG طرح حروف را انتخاب کنید.'));
    if (designWidth <= 0 || designHeight <= 0) return Promise.reject(new Error('عرض و ارتفاع واقعی طرح را به میلی‌متر وارد کنید.'));
    if (sheetWidth <= 100 || sheetHeight <= 100) return Promise.reject(new Error('ابعاد ورق رویه را درست وارد کنید.'));
    if (margin * 2 >= sheetWidth || margin * 2 >= sheetHeight) return Promise.reject(new Error('حاشیه امن از ابعاد ورق بزرگ‌تر است.'));

    updateProgress('مرحله ۱ از ۴: خواندن و بررسی فایل SVG');
    return readFile(file).then(function (text) {
      var prepared = prepareSvg(text);
      if (!prepared.materialJobs.length) throw new Error('هیچ قطعه مشکی یا آبی قابل چیدمان در SVG پیدا نشد.');
      var vectorPerimeters = {
        primary: measureSvgPerimeter(prepared, designWidth, designHeight, 'primary'),
        double: measureSvgPerimeter(prepared, designWidth, designHeight, 'double'),
        pin: measureSvgPerimeter(prepared, designWidth, designHeight, 'pin')
      };
      updateProgress('مرحله ۲ از ۴: تبدیل طرح به قطعات قابل اندازه‌گیری');
      var collected = [];
      var renderedJobs = [];
      var renderMeta = null;
      var sequence = Promise.resolve();
      prepared.materialJobs.forEach(function (job, jobIndex) {
        sequence = sequence.then(function () {
          updateProgress('مرحله ۲ از ۴: خواندن رنگ پلکسی ' + (jobIndex + 1).toLocaleString('fa-IR') + ' از ' + prepared.materialJobs.length.toLocaleString('fa-IR'));
          return renderSvg(prepared, designWidth, designHeight, job);
        }).then(function (rendered) {
          renderMeta = renderMeta || rendered;
          renderedJobs.push(rendered);
          return yieldToBrowser();
        });
      });
      return sequence.then(function () {
        var doubleCutout = renderMeta ? new Uint8Array(renderMeta.width * renderMeta.height) : null;
        var lightingRendered = mergeRenderedJobs(renderedJobs, 'primary') || mergeRenderedJobs(renderedJobs, 'double');
        var lightingComponents = lightingRendered ? extractComponents(lightingRendered) : [];
        var primaryFullAreaMm2 = lightingComponents.reduce(function (sum, component) { return sum + component.areaMm2; }, 0);
        renderedJobs.forEach(function (rendered) {
          if (!doubleCutout || !rendered.job || rendered.job.operation !== 'double') return;
          for (var pixel = 0; pixel < doubleCutout.length; pixel += 1) {
            if (rendered.data[(pixel * 4) + 3] > 28) doubleCutout[pixel] = 1;
          }
        });
        renderedJobs.forEach(function (rendered) {
          var extractionSource = rendered;
          if (doubleCutout && rendered.job && rendered.job.operation === 'primary') {
            var cutData = new Uint8ClampedArray(rendered.data);
            var primaryPixels = 0;
            var overlapPixels = 0;
            for (var pixel = 0; pixel < doubleCutout.length; pixel += 1) {
              if (rendered.data[(pixel * 4) + 3] <= 28) continue;
              primaryPixels += 1;
              if (doubleCutout[pixel]) overlapPixels += 1;
            }
            // A blue contour inside a black contour is an inner cut. If both
            // contours are practically identical, keep the primary layer so
            // ordinary full-surface double letters are not erased.
            if (overlapPixels > 0 && overlapPixels < primaryPixels * 0.96) {
              for (var pixel = 0; pixel < doubleCutout.length; pixel += 1) {
                if (doubleCutout[pixel]) cutData[(pixel * 4) + 3] = 0;
              }
            }
            extractionSource = Object.assign({}, rendered, {data: cutData});
          }
          var jobComponents = extractComponents(extractionSource);
          collected = collected.concat(jobComponents);
        });
        return {rendered: renderMeta, components: collected, lightingComponents: lightingComponents, vectorPerimeters: vectorPerimeters, primaryFullAreaMm2: primaryFullAreaMm2};
      });
    }).then(function (data) {
      return yieldToBrowser().then(function () {
      var rendered = data.rendered;
      var sourceComponents = data.components;
      var primaryComponents = sourceComponents.filter(function (component) { return component.operation === 'primary'; });
      var doubleComponents = sourceComponents.filter(function (component) { return component.operation === 'double'; });
      var components = sourceComponents.slice();
      var step = Math.max(2, Math.min(6, Math.max(sheetWidth, sheetHeight) / 500));
      var usableWidth = Math.floor((sheetWidth - (2 * margin)) / step);
      var usableHeight = Math.floor((sheetHeight - (2 * margin)) / step);
      var items = components.map(function (component, index) { return makePackingItem(component, rendered, step, gap, index); });
      var primaryAreaMm2 = primaryComponents.reduce(function (sum, component) { return sum + component.areaMm2; }, 0);
      var doubleAreaMm2 = doubleComponents.reduce(function (sum, component) { return sum + component.areaMm2; }, 0);
      var materialAreaMm2 = components.reduce(function (sum, component) { return sum + component.areaMm2; }, 0);
      var rasterPrimaryPerimeter = primaryComponents.reduce(function (sum, component) { return sum + component.perimeterMm; }, 0);
      var rasterDoublePerimeter = doubleComponents.reduce(function (sum, component) { return sum + component.perimeterMm; }, 0);
      var primaryPerimeterMm = data.vectorPerimeters.primary > 0 ? data.vectorPerimeters.primary : rasterPrimaryPerimeter;
      var doublePerimeterMm = data.vectorPerimeters.double > 0 ? data.vectorPerimeters.double : rasterDoublePerimeter;
      var pinPerimeterMm = data.vectorPerimeters.pin;
      var perimeterMm = primaryPerimeterMm > 0 ? primaryPerimeterMm : doublePerimeterMm;
      var laserPerimeterMm = primaryPerimeterMm + (2 * doublePerimeterMm) + pinPerimeterMm;
      return packMaterialGroups(items, usableWidth, usableHeight, true, layoutTrials).then(function (sheets) {
        analysisState = {
          components: components,
          sourceComponents: sourceComponents,
          lightingComponents: data.lightingComponents,
          sheets: sheets,
          unplacedItems: sheets.unplaced || [],
          areaMm2: primaryAreaMm2 > 0 ? primaryAreaMm2 : doubleAreaMm2,
          lightingAreaMm2: data.primaryFullAreaMm2 > 0 ? data.primaryFullAreaMm2 : (primaryAreaMm2 > 0 ? primaryAreaMm2 : doubleAreaMm2),
          materialAreaMm2: materialAreaMm2,
          doubleAreaMm2: doubleAreaMm2,
          perimeterMm: perimeterMm,
          primaryPerimeterMm: primaryPerimeterMm,
          doublePerimeterMm: doublePerimeterMm,
          pinPerimeterMm: pinPerimeterMm,
          laserPerimeterMm: laserPerimeterMm,
          primaryParts: primaryComponents.length,
          doubleParts: doubleComponents.length,
          designWidthMm: designWidth,
          designHeightMm: designHeight,
          sheetWidthMm: sheetWidth,
          sheetHeightMm: sheetHeight,
          marginMm: margin,
          gapMm: gap,
          stepMm: step,
          layoutTrials: layoutTrials,
          renderWidth: rendered.width,
          renderHeight: rendered.height,
          renderResolutionX: rendered.resolutionX,
          renderResolutionY: rendered.resolutionY
        };
        updateProgress('مرحله ۴ از ۴: آماده‌سازی پیش‌نمایش و قیمت');
        showAnalysis(analysisState);
        saveLastValues();
      });
      });
    });
  }

  function saveRates() {
    window.clearTimeout(saveRatesTimer);
    var status = ratesForm.querySelector('[data-letter-rates-status]');
    status.textContent = 'در حال ذخیره نرخ‌ها…';
    status.className = 'manager-pricing-rates__status is-saving';
    var body = new URLSearchParams({action: 'zigurat_save_letter_rates', nonce: ratesForm.dataset.ratesNonce || ''});
    var moneyNames = [
      'plexi_sqm_rate', 'metal_sheet_07_sqm_rate', 'edge_swedish_material_rate', 'edge_swedish_labor_rate',
      'edge_plastic_material_rate', 'edge_plastic_labor_rate', 'edge_channelium_material_rate',
      'edge_channelium_labor_rate', 'edge_metal_material_rate', 'edge_metal_labor_rate',
      'metal_powder_coating_rate', 'double_layer_labor_rate',
      'pvc_rate', 'plexi_cut_rate', 'pvc_cut_rate', 'glue_rate',
      'smd_block_rate', 'smd_lens_rate', 'smd_roll_rate',
      'transformer_60_rate', 'transformer_100_rate', 'transformer_120_rate',
      'transformer_200_rate', 'transformer_300_rate', 'transformer_400_rate'
    ];
    var decimalNames = [
      'sheet_width_mm', 'sheet_height_mm', 'smd_block_units_per_square_meter',
      'smd_lens_units_per_square_meter',
      'smd_block_width_mm', 'smd_block_height_mm', 'smd_block_led_count',
      'smd_lens_width_mm', 'smd_lens_height_mm', 'smd_lens_led_count',
      'smd_roll_strip_width_mm', 'smd_roll_watts_per_meter', 'smd_roll_length_m',
      'smd_roll_cut_interval_mm', 'smd_roll_waste_percent', 'smd_roll_transformer_reserve_percent',
      'smd_roll_wall_clearance_mm', 'smd_roll_row_spacing_mm',
      'transformer_60_capacity', 'transformer_100_capacity', 'transformer_120_capacity',
      'transformer_200_capacity', 'transformer_300_capacity', 'transformer_400_capacity',
      'cut_gap_mm', 'sheet_margin_mm'
    ];
    moneyNames.forEach(function (name) { body.append(name, money(rateField(name).value)); });
    decimalNames.forEach(function (name) { body.append(name, decimal(rateField(name).value)); });
    ['active_edge_type', 'active_smd_type', 'active_transformer_type'].forEach(function (name) {
      body.append(name, rateField(name).value);
    });
    fetch(ratesForm.dataset.ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) throw new Error('save_failed');
        status.textContent = 'نرخ‌ها ذخیره شدند.';
        status.className = 'manager-pricing-rates__status is-saved';
        updateSelectedRateSummaries();
        calculateCosts();
      });
    }).catch(function () {
      status.textContent = 'ذخیره نرخ‌ها انجام نشد؛ دوباره تلاش کنید.';
      status.className = 'manager-pricing-rates__status is-error';
    });
  }
  function scheduleRatesSave() {
    window.clearTimeout(saveRatesTimer);
    saveRatesTimer = window.setTimeout(saveRates, 550);
  }

  function saveLastValues() {
    window.clearTimeout(saveValuesTimer);
    var body = new URLSearchParams({
      action: 'zigurat_save_letter_last_values', nonce: form.dataset.valuesNonce || '',
      installation: money(field('installation').value), travel: money(field('travel').value),
      wire_supplies: money(field('wire_supplies').value),
      profit_percent: decimal(field('profit_percent').value),
      insurance_tax_percent: decimal(field('insurance_tax_percent').value),
      use_transformer: field('use_transformer').checked ? 1 : 0,
      installation_mode: field('installation_mode').value === 'perimeter' ? 'perimeter' : 'fixed',
      layout_trials: Number(field('layout_trials').value) || 10,
      edge_type: field('edge_type').value,
      smd_type: field('smd_type').value
    });
    fetch(form.dataset.ajaxUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
      body: body.toString()
    }).catch(function () {});
  }
  function scheduleValuesSave() {
    window.clearTimeout(saveValuesTimer);
    saveValuesTimer = window.setTimeout(saveLastValues, 550);
  }

  function showSelectedRatePanel(group, value) {
    ratesForm.querySelectorAll('[data-letter-' + group + '-rate-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-letter-' + group + '-rate-panel') !== value;
    });
  }

  function updateSelectedRateSummaries() {
    var edgeType = edgeLabels[field('edge_type').value] ? field('edge_type').value : 'swedish';
    var edgeSummary = form.querySelector('[data-letter-edge-rate-summary]');
    if (edgeSummary) {
      edgeSummary.textContent = 'قیمت هر متر: ' + formatMoney(money(rateField('edge_' + edgeType + '_material_rate').value)) + '؛ اجرت هر متر: ' + formatMoney(money(rateField('edge_' + edgeType + '_labor_rate').value));
      if (edgeType === 'metal') {
        edgeSummary.textContent += '؛ رنگ کوره‌ای هر مترمربع: ' + formatMoney(money(rateField('metal_powder_coating_rate').value));
      }
    }
    var smdType = smdLabels[field('smd_type').value] ? field('smd_type').value : 'none';
    var smdSummary = form.querySelector('[data-letter-smd-rate-summary]');
    if (smdSummary) {
      if (smdType === 'none') {
        smdSummary.textContent = 'در محاسبه منظور نمی‌شود.';
      } else if (smdType === 'roll') {
        smdSummary.textContent = 'قیمت هر متر با اجرت نصب: ' + formatMoney(money(rateField('smd_roll_rate').value))
          + '؛ عرض نوار: ' + formatMeasure(decimal(rateField('smd_roll_strip_width_mm').value), 1) + ' میلی‌متر'
          + '؛ حریم دیواره: ' + formatMeasure(decimal(rateField('smd_roll_wall_clearance_mm').value), 1) + ' میلی‌متر'
          + '؛ حداکثر فاصله نوارها: ' + formatMeasure(decimal(rateField('smd_roll_row_spacing_mm').value), 1) + ' میلی‌متر مرکز تا مرکز'
          + '؛ توان: ' + formatMeasure(decimal(rateField('smd_roll_watts_per_meter').value), 1) + ' وات بر متر';
      } else {
        smdSummary.textContent = 'قیمت هر واحد با اجرت نصب: ' + formatMoney(money(rateField('smd_' + smdType + '_rate').value))
          + '؛ تراکم: ' + formatMeasure(decimal(rateField('smd_' + smdType + '_units_per_square_meter').value), 1) + ' واحد در مترمربع رویه';
      }
      if (smdType === 'block' || smdType === 'lens') {
        smdSummary.textContent += '؛ اندازه بلوک' + (smdType === 'lens' ? ' لنزدار' : '') + ': ' + formatMeasure(decimal(rateField('smd_' + smdType + '_width_mm').value) || 75, 1) + ' × ' + formatMeasure(decimal(rateField('smd_' + smdType + '_height_mm').value) || 15, 1) + ' میلی‌متر';
      }
    }
  }

  function updateInstallationModeUi() {
    var isPerimeter = field('installation_mode').value === 'perimeter';
    var label = form.querySelector('[data-letter-installation-input-label]');
    if (label) label.textContent = isPerimeter ? 'نرخ نصب هر متر محیط (ریال)' : 'هزینه نصب کلی (ریال)';
  }

  ratesForm.querySelectorAll('input, select').forEach(function (input) {
    input.addEventListener('input', function () {
      updateSelectedRateSummaries();
      if (analysisState) calculateCosts();
      if (String(input.value).trim() !== '') scheduleRatesSave();
    });
    input.addEventListener('change', function () {
      if (analysisState) calculateCosts();
      if (String(input.value).trim() !== '') saveRates();
    });
  });

  rateField('active_edge_type').addEventListener('change', function () { showSelectedRatePanel('edge', rateField('active_edge_type').value); });
  rateField('active_smd_type').addEventListener('change', function () { showSelectedRatePanel('smd', rateField('active_smd_type').value); });
  rateField('active_transformer_type').addEventListener('change', function () { showSelectedRatePanel('transformer', rateField('active_transformer_type').value); });

  ['installation','travel','wire_supplies','profit_percent','insurance_tax_percent'].forEach(function (name) {
    var input = field(name);
    input.addEventListener('input', function () { calculateCosts(); if (input.value.trim() !== '') scheduleValuesSave(); });
    input.addEventListener('change', function () { calculateCosts(); if (input.value.trim() !== '') saveLastValues(); });
  });
  field('use_transformer').addEventListener('change', function () {
    calculateCosts();
    saveLastValues();
  });
  field('installation_mode').addEventListener('change', function () {
    updateInstallationModeUi();
    calculateCosts();
    saveLastValues();
  });
  field('layout_trials').addEventListener('change', saveLastValues);
  field('edge_type').addEventListener('change', function () {
    rateField('active_edge_type').value = field('edge_type').value;
    showSelectedRatePanel('edge', field('edge_type').value);
    updateSelectedRateSummaries();
    calculateCosts();
    saveLastValues();
  });
  field('smd_type').addEventListener('change', function () {
    if (field('smd_type').value !== 'none') {
      rateField('active_smd_type').value = field('smd_type').value;
      showSelectedRatePanel('smd', field('smd_type').value);
    }
    updateSelectedRateSummaries();
    calculateCosts();
    saveLastValues();
  });
  rateField('active_edge_type').value = field('edge_type').value;
  if (field('smd_type').value !== 'none') rateField('active_smd_type').value = field('smd_type').value;
  showSelectedRatePanel('edge', rateField('active_edge_type').value);
  showSelectedRatePanel('smd', rateField('active_smd_type').value);
  showSelectedRatePanel('transformer', rateField('active_transformer_type').value);
  updateSelectedRateSummaries();
  updateInstallationModeUi();

  function handleSelectedFile(file) {
    analysisState = null;
    form.querySelector('[data-letter-analysis]').hidden = true;
    clearFilePreview();
    if (!file) return Promise.resolve(false);
    if (!/\.svg$/i.test(file.name || '') && file.type !== 'image/svg+xml') {
      showError('فقط فایل SVG قابل تحلیل است.');
      return Promise.resolve(false);
    }
    form.querySelector('[data-letter-file-name]').textContent = file.name;
    var task = readFile(file).then(function (text) {
      var prepared = prepareSvg(text);
      showFilePreview(prepared, file);
      if (prepared.widthMm > 0 && prepared.heightMm > 0) {
        field('design_width_mm').value = Number(prepared.widthMm.toFixed(2)).toLocaleString('fa-IR', {maximumFractionDigits: 2});
        field('design_height_mm').value = Number(prepared.heightMm.toFixed(2)).toLocaleString('fa-IR', {maximumFractionDigits: 2});
        sourceRatio = prepared.widthMm / prepared.heightMm;
      } else {
        sourceRatio = prepared.viewBox[2] / prepared.viewBox[3];
        if (!decimal(field('design_width_mm').value)) field('design_width_mm').value = '';
        if (!decimal(field('design_height_mm').value)) field('design_height_mm').value = '';
      }
      clearError();
      return true;
    });
    task.catch(function (error) { showError(error.message); });
    return task;
  }

  field('letter_svg').addEventListener('change', function () {
    droppedFile = null;
    field('letter_svg').required = true;
    var file = field('letter_svg').files && field('letter_svg').files[0];
    handleSelectedFile(file).catch(function () {});
  });

  var uploadArea = form.querySelector('[data-letter-upload]');
  ['dragenter', 'dragover'].forEach(function (eventName) {
    uploadArea.addEventListener(eventName, function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
      uploadArea.classList.add('is-dragging');
    });
  });
  ['dragleave', 'dragend'].forEach(function (eventName) {
    uploadArea.addEventListener(eventName, function (event) {
      event.preventDefault();
      event.stopPropagation();
      uploadArea.classList.remove('is-dragging');
    });
  });
  uploadArea.addEventListener('drop', function (event) {
    event.preventDefault();
    event.stopPropagation();
    uploadArea.classList.remove('is-dragging');
    var file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
    if (!file) return;
    droppedFile = file;
    try {
      var transfer = new DataTransfer();
      transfer.items.add(file);
      field('letter_svg').files = transfer.files;
      field('letter_svg').required = true;
    } catch (error) {
      field('letter_svg').required = false;
    }
    handleSelectedFile(file).catch(function () {});
  });

  field('design_width_mm').addEventListener('change', function () {
    if (sourceRatio > 0 && decimal(field('design_width_mm').value) > 0 && decimal(field('design_height_mm').value) <= 0) {
      field('design_height_mm').value = formatMeasure(decimal(field('design_width_mm').value) / sourceRatio);
    }
  });
  field('design_height_mm').addEventListener('change', function () {
    if (sourceRatio > 0 && decimal(field('design_height_mm').value) > 0 && decimal(field('design_width_mm').value) <= 0) {
      field('design_width_mm').value = formatMeasure(decimal(field('design_height_mm').value) * sourceRatio);
    }
  });

  var saveEstimateButton = form.querySelector('[data-letter-estimate-save]');
  var newEstimateButton = form.querySelector('[data-letter-estimate-new]');
  var printEstimateButton = form.querySelector('[data-letter-estimate-print]');
  var estimateList = document.querySelector('[data-letter-estimate-list]');
  var estimateListStatus = document.querySelector('[data-letter-estimate-list-status]');
  var estimateSection = document.querySelector('[data-letter-estimates]');
  var estimateSearch = document.querySelector('[data-letter-estimate-search]');
  var estimateSearchClear = document.querySelector('[data-letter-estimate-search-clear]');

  if (saveEstimateButton) {
    saveEstimateButton.addEventListener('click', function () {
      var projectName = String(field('estimate_project_name').value || '').trim();
      if (!projectName) {
        setEstimateStatus('برای ذخیره، ابتدا نام پروژه را وارد کنید.', 'error');
        field('estimate_project_name').focus();
        return;
      }
      var snapshot;
      try {
        snapshot = buildEstimateSnapshot();
      } catch (error) {
        setEstimateStatus(error.message, 'error');
        return;
      }
      saveEstimateButton.disabled = true;
      setEstimateStatus('در حال ذخیره محاسبه…', 'saving');
      estimateRequest('zigurat_save_pricing_estimate', {
        estimate_id: field('estimate_id').value || 0,
        project_name: projectName,
        snapshot: JSON.stringify(snapshot)
      }).then(function (data) {
        field('estimate_id').value = data.id;
        var mode = form.querySelector('[data-letter-estimate-mode]');
        if (mode) mode.textContent = 'این برآورد ذخیره شده و تغییرات بعدی روی همین رکورد ثبت می‌شود.';
        if (newEstimateButton) newEstimateButton.hidden = false;
        loadEstimateList(1).catch(function () {});
        setEstimateStatus(data.message || 'محاسبه ذخیره شد.', 'success');
      }).catch(function (error) {
        setEstimateStatus(error.message, 'error');
      }).finally(function () { saveEstimateButton.disabled = false; });
    });
  }

  if (newEstimateButton) {
    newEstimateButton.addEventListener('click', function () {
      field('estimate_id').value = 0;
      field('estimate_project_name').value = '';
      var mode = form.querySelector('[data-letter-estimate-mode]');
      if (mode) mode.textContent = 'به‌عنوان یک برآورد جدید ذخیره می‌شود.';
      newEstimateButton.hidden = true;
      setEstimateStatus('نام پروژه جدید را وارد و ذخیره کنید.', 'success');
      field('estimate_project_name').focus();
    });
  }

  if (printEstimateButton) {
    printEstimateButton.addEventListener('click', function () {
      try {
        openEstimatePrint(String(field('estimate_project_name').value || '').trim() || 'برآورد جدید', buildEstimateSnapshot());
      } catch (error) {
        setEstimateStatus(error.message, 'error');
      }
    });
  }

  if (estimateList) {
    estimateList.addEventListener('click', function (event) {
      var loadButton = event.target.closest('[data-letter-estimate-load]');
      var savedPrintButton = event.target.closest('[data-letter-estimate-print-saved]');
      if (!loadButton && !savedPrintButton) return;
      event.preventDefault();
      var estimateId = loadButton ? loadButton.dataset.letterEstimateLoad : savedPrintButton.dataset.letterEstimatePrintSaved;
      var printWindow = savedPrintButton ? window.open('', '_blank') : null;
      if (savedPrintButton && !printWindow) {
        setEstimateStatus('مرورگر پنجره چاپ را مسدود کرده است. اجازه Pop-up را فعال کنید.', 'error');
        return;
      }
      var clickedButton = loadButton || savedPrintButton;
      var originalButtonText = clickedButton.textContent;
      clickedButton.disabled = true;
      clickedButton.textContent = loadButton ? 'در حال بازیابی…' : 'در حال آماده‌سازی…';
      if (estimateListStatus) {
        estimateListStatus.textContent = loadButton ? 'در حال بازیابی محاسبه ذخیره‌شده…' : 'در حال آماده‌سازی گزارش…';
        estimateListStatus.className = 'manager-pricing-estimates__status is-saving';
      }
      if (printWindow) printWindow.document.write('<p dir="rtl" style="font-family:Tahoma;padding:30px">در حال آماده‌سازی گزارش…</p>');
      estimateRequest('zigurat_get_pricing_estimate', {estimate_id: estimateId}).then(function (data) {
        if (loadButton) return restoreEstimate(data).then(function () {
          if (estimateListStatus) {
            estimateListStatus.textContent = 'محاسبه برای ویرایش بازیابی شد.';
            estimateListStatus.className = 'manager-pricing-estimates__status is-success';
          }
        });
        openEstimatePrint(data.project_name, data.snapshot, printWindow);
        if (estimateListStatus) {
          estimateListStatus.textContent = 'گزارش چاپ آماده شد.';
          estimateListStatus.className = 'manager-pricing-estimates__status is-success';
        }
      }).catch(function (error) {
        if (printWindow) printWindow.close();
        setEstimateStatus(error.message, 'error');
        if (estimateListStatus) {
          estimateListStatus.textContent = error.message;
          estimateListStatus.className = 'manager-pricing-estimates__status is-error';
        }
      }).finally(function () {
        clickedButton.disabled = false;
        clickedButton.textContent = originalButtonText;
      });
    });
  }

  if (estimateSection) {
    estimateSection.addEventListener('click', function (event) {
      var pageButton = event.target.closest('[data-letter-estimate-page]');
      if (!pageButton || pageButton.disabled) return;
      var currentPage = Math.max(1, Number(estimateSection.dataset.currentPage || 1));
      var totalPages = Math.max(1, Number(estimateSection.dataset.totalPages || 1));
      var nextPage = pageButton.dataset.letterEstimatePage === 'next' ? currentPage + 1 : currentPage - 1;
      nextPage = Math.max(1, Math.min(totalPages, nextPage));
      pageButton.disabled = true;
      loadEstimateList(nextPage).catch(function () {});
    });
  }

  if (estimateSearch) {
    estimateSearch.addEventListener('input', function () {
      window.clearTimeout(estimateSearchTimer);
      if (estimateSearchClear) estimateSearchClear.hidden = estimateSearch.value.trim() === '';
      estimateSearchTimer = window.setTimeout(function () {
        loadEstimateList(1).catch(function () {});
      }, 400);
    });
  }

  if (estimateSearchClear) {
    estimateSearchClear.addEventListener('click', function () {
      estimateSearch.value = '';
      estimateSearchClear.hidden = true;
      loadEstimateList(1).catch(function () {});
      estimateSearch.focus();
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    setBusy(true);
    window.requestAnimationFrame(function () {
      analyze().catch(function (error) { showError(error.message || 'تحلیل فایل انجام نشد.'); }).finally(function () { setBusy(false); });
    });
  });
}());
