(function () {
  'use strict';

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
      sourceIndex: item.sourceIndex,
      width: width,
      height: height,
      collisionMask: collision,
      rotation: normalizedAngle,
      areaMm2: item.areaMm2,
      perimeterMm: item.perimeterMm,
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
    return {width: width, height: height, occupied: new Uint8Array(width * height), rowPrefix: rowPrefix, placements: [], usedBottom: 0, usedRight: 0};
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
        for (var x = placement.x + run[0]; x <= placement.x + run[1]; x += 1) sheet.occupied[(sheetY * sheet.width) + x] = 1;
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

  function seededItemRank(item, seed) {
    var value = Math.imul((item.sourceIndex + 1) ^ seed, 2654435761) >>> 0;
    value ^= value >>> 16;
    value = Math.imul(value, 2246822519) >>> 0;
    value ^= value >>> 13;
    return value >>> 0;
  }

  function createSeededSorter(seed) {
    return function (a, b) {
      var rankDifference = seededItemRank(a, seed) - seededItemRank(b, seed);
      return rankDifference || b.cellCount - a.cellCount || a.sourceIndex - b.sourceIndex;
    };
  }

  function packInOrder(items, sheetWidth, sheetHeight, allowRotation, sorter) {
    var ordered = items.slice().sort(sorter);
    var sheets = [];
    var unplaced = [];
    ordered.forEach(function (baseItem) {
      var orientations = baseItem.orientations || buildOrientations(baseItem, allowRotation);
      var selected = null;
      var selectedSheet = null;
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

  function packItems(items, sheetWidth, sheetHeight, allowRotation, requestedTrials, groupLabel, groupColor, groupIndex, groupCount) {
    var trialCount = [5, 10, 20, 30].indexOf(Number(requestedTrials)) !== -1 ? Number(requestedTrials) : 10;
    var sorters = [
      function (a, b) { return b.cellCount - a.cellCount; },
      function (a, b) { return Math.max(b.width, b.height) - Math.max(a.width, a.height); },
      function (a, b) { return b.height - a.height || b.width - a.width; },
      function (a, b) { return b.width - a.width || b.height - a.height; },
      function (a, b) { return b.perimeterMm - a.perimeterMm; }
    ];
    while (sorters.length < trialCount) sorters.push(createSeededSorter(7919 + (sorters.length * 104729)));
    items.forEach(function (item) { item.orientations = buildOrientations(item, allowRotation); });
    var best = null;
    for (var index = 0; index < sorters.length; index += 1) {
      self.postMessage({type: 'progress', group: groupIndex + 1, groups: groupCount, trial: index + 1, trials: sorters.length, label: groupLabel || '', color: groupColor || ''});
      var candidate = packInOrder(items, sheetWidth, sheetHeight, allowRotation, sorters[index]);
      var usedBottom = candidate.reduce(function (sum, sheet) { return sum + sheet.usedBottom; }, 0);
      var unplacedCount = (candidate.unplaced || []).length;
      var score = (unplacedCount * 1000000000000000) + (candidate.length * 1000000000) + usedBottom;
      if (!best || score < best.score) best = {sheets: candidate, score: score};
    }
    return best ? best.sheets : [];
  }

  function packMaterialGroups(items, sheetWidth, sheetHeight, allowRotation, requestedTrials) {
    var groups = {};
    items.forEach(function (item) {
      var key = item.materialKey || '#000000';
      if (!groups[key]) groups[key] = {key: key, color: item.materialColor || '#000000', label: item.materialLabel || key, items: []};
      groups[key].items.push(item);
    });
    var orderedGroups = Object.keys(groups).map(function (key) { return groups[key]; });
    var allSheets = [];
    var allUnplaced = [];
    orderedGroups.forEach(function (group, groupIndex) {
      var sheets = packItems(group.items, sheetWidth, sheetHeight, allowRotation, requestedTrials, group.label, group.color, groupIndex, orderedGroups.length);
      sheets.forEach(function (sheet) {
        sheet.materialKey = group.key;
        sheet.materialColor = group.color;
        sheet.materialLabel = group.label;
      });
      (sheets.unplaced || []).forEach(function (item) {
        allUnplaced.push({itemIndex: item.sourceIndex, materialKey: group.key, materialColor: group.color, materialLabel: group.label});
      });
      allSheets = allSheets.concat(sheets);
    });
    return {sheets: allSheets.map(function (sheet) {
      return {
        width: sheet.width,
        height: sheet.height,
        usedBottom: sheet.usedBottom,
        usedRight: sheet.usedRight,
        materialKey: sheet.materialKey,
        materialColor: sheet.materialColor,
        materialLabel: sheet.materialLabel,
        placements: sheet.placements.map(function (placement) {
          return {x: placement.x, y: placement.y, itemIndex: placement.item.sourceIndex, width: placement.item.width, height: placement.item.height, rotation: placement.item.rotation || 0};
        })
      };
    }), unplaced: allUnplaced};
  }

  self.onmessage = function (event) {
    var data = event.data || {};
    if (data.type !== 'pack') return;
    try {
      var items = (data.items || []).map(function (raw) {
        return addPackingProfiles({
          sourceIndex: raw.sourceIndex,
          width: raw.width,
          height: raw.height,
          collisionMask: new Uint8Array(raw.collisionBuffer),
          areaMm2: raw.areaMm2,
          perimeterMm: raw.perimeterMm,
          previewWidthMm: raw.previewWidthMm,
          previewHeightMm: raw.previewHeightMm,
          materialKey: raw.materialKey || '#000000',
          materialColor: raw.materialColor || '#000000',
          materialLabel: raw.materialLabel || raw.materialKey || '#000000',
          rotation: 0
        });
      });
      var result = packMaterialGroups(items, data.sheetWidth, data.sheetHeight, data.allowRotation, data.requestedTrials);
      self.postMessage({type: 'result', result: result});
    } catch (error) {
      self.postMessage({type: 'error', message: error && error.message ? error.message : 'پردازش Worker ناموفق بود.'});
    }
  };
})();
