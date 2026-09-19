/**
 * Storefront Settings → Shop menu: recursive nestable Physical column builder (SortableJS).
 * Groups may nest under groups (server enforces max depth 5).
 * Horizontal drag: right = nest under previous sibling group; left = un-nest (after parent).
 */
(function ($) {
  "use strict";

  var MAX_DEPTH = 5;
  var INDENT_THRESHOLD_PX = 36;

  function parseJsonScript(id, fallback) {
    var el = document.getElementById(id);
    if (!el) return fallback;
    try {
      return JSON.parse(el.textContent || "null") || fallback;
    } catch (e) {
      return fallback;
    }
  }

  function uid() {
    return "sm_" + Math.random().toString(36).slice(2, 10);
  }

  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function eventClientX(e) {
    if (!e) return 0;
    if (typeof e.clientX === "number") return e.clientX;
    if (e.touches && e.touches[0]) return e.touches[0].clientX;
    if (e.changedTouches && e.changedTouches[0]) return e.changedTouches[0].clientX;
    return 0;
  }

  function initShopMenuBuilder() {
    var $root = $("#storefront_shop_menu_builder");
    if (!$root.length || typeof Sortable === "undefined") {
      return;
    }

    var categories = parseJsonScript("sf-shop-menu-categories-json", []);
    var tree = parseJsonScript("sf-shop-menu-physical-json", []);
    if (!Array.isArray(tree)) tree = [];

    var catById = {};
    categories.forEach(function (c) {
      catById[String(c.id)] = c;
    });

    var $select = $("#sf_shop_menu_cat_select");
    categories.forEach(function (c) {
      $select.append(
        $("<option>")
          .val(c.id)
          .text(c.name + (c.slug ? " (" + c.slug + ")" : ""))
      );
    });

    var $tree = $("#sf_shop_menu_tree");
    var $empty = $root.find(".sf-shop-menu-empty");
    var $hidden = $("#shop_menu_physical");

    var indentDrag = {
      active: false,
      startX: 0,
      item: null,
      ghost: null,
    };

    function itemDepth($li) {
      return $li.parents("li.sf-shop-menu-item--group").length + 1;
    }

    /** Depth span of a subtree (1 for a link; group + deepest descendant). */
    function subtreeDepth($li) {
      if (!$li.hasClass("sf-shop-menu-item--group")) {
        return 1;
      }
      var maxChild = 0;
      $li
        .children(".panel-body")
        .children(".sf-shop-menu-children")
        .children("li.sf-shop-menu-item")
        .each(function () {
          maxChild = Math.max(maxChild, subtreeDepth($(this)));
        });
      return 1 + maxChild;
    }

    function groupChildrenUl($group) {
      return $group.children(".panel-body").children(".sf-shop-menu-children");
    }

    function syncHidden() {
      var data = serializeList($tree);
      $hidden.val(JSON.stringify(data));
      $empty.toggle(data.length === 0);
    }

    function serializeItem($li) {
      var type = $li.data("type");
      if (type === "group") {
        var children = [];
        groupChildrenUl($li)
          .children("li.sf-shop-menu-item")
          .each(function () {
            children.push(serializeItem($(this)));
          });
        return {
          id: String($li.data("id") || uid()),
          type: "group",
          label: {
            en: String($li.find("> .panel-body > .row [data-field='group_en']").val() || ""),
            ar: String($li.find("> .panel-body > .row [data-field='group_ar']").val() || ""),
          },
          children: children,
        };
      }
      return {
        id: String($li.data("id") || uid()),
        type: "link",
        category_id: parseInt($li.data("category-id"), 10) || 0,
        label: {
          en: String($li.find("> .panel-body [data-field='label_en']").val() || ""),
          ar: String($li.find("> .panel-body [data-field='label_ar']").val() || ""),
        },
      };
    }

    function serializeList($ul) {
      var out = [];
      $ul.children("li.sf-shop-menu-item").each(function () {
        out.push(serializeItem($(this)));
      });
      return out;
    }

    function linkRowHtml(node) {
      var catId = node.category_id || 0;
      var cat = catById[String(catId)];
      var title = cat ? cat.name : "Category #" + catId;
      var labelEn = (node.label && node.label.en) || "";
      var labelAr = (node.label && node.label.ar) || "";
      return (
        '<li class="sf-shop-menu-item sf-shop-menu-item--link panel panel-default" data-type="link" data-id="' +
        escapeHtml(node.id || uid()) +
        '" data-category-id="' +
        escapeHtml(catId) +
        '">' +
        '<div class="panel-body" style="padding:8px 10px;">' +
        '<div class="sf-shop-menu-handle"><i class="fa fa-bars"></i> <strong>' +
        escapeHtml(title) +
        "</strong>" +
        ' <button type="button" class="btn btn-xs btn-danger pull-right sf-shop-menu-remove"><i class="fa fa-trash"></i></button></div>' +
        '<div class="row" style="margin-top:6px;">' +
        '<div class="col-xs-6"><input type="text" class="form-control input-sm" data-field="label_en" placeholder="Label EN (optional)" value="' +
        escapeHtml(labelEn) +
        '" maxlength="80"></div>' +
        '<div class="col-xs-6"><input type="text" class="form-control input-sm" data-field="label_ar" placeholder="Label AR (optional)" value="' +
        escapeHtml(labelAr) +
        '" maxlength="80" dir="rtl"></div>' +
        "</div></div></li>"
      );
    }

    function groupRowHtml(node) {
      var labelEn = (node.label && node.label.en) || "";
      var labelAr = (node.label && node.label.ar) || "";
      var childrenHtml = "";
      (node.children || []).forEach(function (child) {
        childrenHtml += child.type === "group" ? groupRowHtml(child) : linkRowHtml(child);
      });
      return (
        '<li class="sf-shop-menu-item sf-shop-menu-item--group panel panel-primary" data-type="group" data-id="' +
        escapeHtml(node.id || uid()) +
        '">' +
        '<div class="panel-heading" style="padding:6px 10px;">' +
        '<span class="sf-shop-menu-handle"><i class="fa fa-bars"></i> Group</span>' +
        ' <button type="button" class="btn btn-xs btn-danger pull-right sf-shop-menu-remove"><i class="fa fa-trash"></i></button>' +
        "</div>" +
        '<div class="panel-body" style="padding:8px 10px;">' +
        '<div class="row">' +
        '<div class="col-xs-6"><input type="text" class="form-control input-sm" data-field="group_en" placeholder="Group title EN" value="' +
        escapeHtml(labelEn) +
        '" maxlength="80"></div>' +
        '<div class="col-xs-6"><input type="text" class="form-control input-sm" data-field="group_ar" placeholder="Group title AR" value="' +
        escapeHtml(labelAr) +
        '" maxlength="80" dir="rtl"></div>' +
        "</div>" +
        '<ul class="sf-shop-menu-children list-unstyled" style="margin:10px 0 0;min-height:28px;">' +
        childrenHtml +
        "</ul>" +
        '<p class="help-block" style="margin:4px 0 0;">Drop links or groups here (max ' +
        MAX_DEPTH +
        " levels)</p>" +
        "</div></li>"
      );
    }

    function canPlaceUnderGroup($drag, $group) {
      var placeDepth = itemDepth($group) + 1;
      if (placeDepth > MAX_DEPTH) {
        return false;
      }
      if ($drag.hasClass("sf-shop-menu-item--group")) {
        return placeDepth + subtreeDepth($drag) - 1 <= MAX_DEPTH;
      }
      return true;
    }

    /**
     * Nest under the previous sibling when it is a group (WordPress-style indent).
     * Returns true when the DOM changed.
     */
    function tryIndent($li) {
      var $prev = $li.prev("li.sf-shop-menu-item");
      if (!$prev.length || !$prev.hasClass("sf-shop-menu-item--group")) {
        return false;
      }
      if (!canPlaceUnderGroup($li, $prev)) {
        return false;
      }
      groupChildrenUl($prev).append($li);
      return true;
    }

    /**
     * Move out of the parent group to sit after it (WordPress-style outdent).
     */
    function tryOutdent($li) {
      var $parentUl = $li.parent();
      if (!$parentUl.hasClass("sf-shop-menu-children")) {
        return false;
      }
      var $parentGroup = $parentUl.closest("li.sf-shop-menu-item--group");
      if (!$parentGroup.length) {
        return false;
      }
      $parentGroup.after($li);
      return true;
    }

    function applyHorizontalNesting($li, dx) {
      var steps = Math.floor(Math.abs(dx) / INDENT_THRESHOLD_PX);
      if (steps < 1) {
        return false;
      }
      var changed = false;
      var i;
      if (dx >= INDENT_THRESHOLD_PX) {
        for (i = 0; i < steps; i++) {
          if (!tryIndent($li)) {
            break;
          }
          changed = true;
        }
      } else if (dx <= -INDENT_THRESHOLD_PX) {
        for (i = 0; i < steps; i++) {
          if (!tryOutdent($li)) {
            break;
          }
          changed = true;
        }
      }
      return changed;
    }

    function setIndentHint(dx) {
      var el = indentDrag.ghost || indentDrag.item;
      if (!el) return;
      var hint = 0;
      if (dx >= INDENT_THRESHOLD_PX) {
        hint = Math.min(72, Math.floor(dx / INDENT_THRESHOLD_PX) * 18);
      } else if (dx <= -INDENT_THRESHOLD_PX) {
        hint = -Math.min(72, Math.floor(Math.abs(dx) / INDENT_THRESHOLD_PX) * 18);
      }
      el.style.transform = hint ? "translateX(" + hint + "px)" : "";
      el.classList.toggle("sf-shop-menu-item--indent-hint", hint > 0);
      el.classList.toggle("sf-shop-menu-item--outdent-hint", hint < 0);
    }

    function clearIndentHint() {
      var el = indentDrag.ghost || indentDrag.item;
      if (el) {
        el.style.transform = "";
        el.classList.remove("sf-shop-menu-item--indent-hint", "sf-shop-menu-item--outdent-hint");
      }
    }

    function onIndentPointerMove(e) {
      if (!indentDrag.active) return;
      setIndentHint(eventClientX(e) - indentDrag.startX);
    }

    function stopIndentTracking() {
      indentDrag.active = false;
      document.removeEventListener("pointermove", onIndentPointerMove, true);
      document.removeEventListener("mousemove", onIndentPointerMove, true);
      document.removeEventListener("touchmove", onIndentPointerMove, true);
      clearIndentHint();
      indentDrag.item = null;
      indentDrag.ghost = null;
    }

    function startIndentTracking(evt) {
      indentDrag.active = true;
      indentDrag.startX = eventClientX(evt.originalEvent);
      indentDrag.item = evt.item;
      indentDrag.ghost = evt.clone || document.querySelector(".sortable-ghost") || evt.item;
      document.addEventListener("pointermove", onIndentPointerMove, true);
      document.addEventListener("mousemove", onIndentPointerMove, true);
      document.addEventListener("touchmove", onIndentPointerMove, true);
    }

    function render() {
      var html = "";
      tree.forEach(function (node) {
        html += node.type === "group" ? groupRowHtml(node) : linkRowHtml(node);
      });
      $tree.html(html);
      bindSortables();
      syncHidden();
    }

    function bindSortables() {
      $tree.find(".sf-shop-menu-children").addBack().each(function () {
        var $el = $(this);
        if ($el.data("sortable")) {
          $el.data("sortable").destroy();
          $el.removeData("sortable");
        }
      });

      function makeSortable(el) {
        var sortable = Sortable.create(el, {
          group: {
            name: "shop-menu",
            pull: true,
            put: function (to, from, dragEl) {
              var $drag = $(dragEl);
              var $to = $(to.el);
              var parentGroup = $to.closest("li.sf-shop-menu-item--group");
              if (!parentGroup.length) {
                return true;
              }
              return canPlaceUnderGroup($drag, parentGroup);
            },
          },
          handle: ".sf-shop-menu-handle",
          animation: 150,
          fallbackOnBody: true,
          swapThreshold: 0.65,
          onStart: function (evt) {
            startIndentTracking(evt);
          },
          onEnd: function (evt) {
            var endX = eventClientX(evt.originalEvent);
            var dx = endX - indentDrag.startX;
            var $li = $(evt.item);
            stopIndentTracking();
            if (applyHorizontalNesting($li, dx)) {
              bindSortables();
            }
            syncHidden();
          },
          onAdd: function () {
            bindSortables();
            syncHidden();
          },
          onUpdate: syncHidden,
          onSort: syncHidden,
        });
        $(el).data("sortable", sortable);
      }

      makeSortable($tree[0]);
      $tree.find(".sf-shop-menu-children").each(function () {
        makeSortable(this);
      });
    }

    $tree.on("click", ".sf-shop-menu-remove", function (e) {
      e.preventDefault();
      $(this).closest("li.sf-shop-menu-item").remove();
      syncHidden();
    });

    $tree.on("input change", "input", syncHidden);

    $("#sf_shop_menu_add_link").on("click", function () {
      var id = $select.val();
      if (!id || !catById[String(id)]) return;
      $tree.append(
        linkRowHtml({
          id: uid(),
          type: "link",
          category_id: parseInt(id, 10),
          label: { en: "", ar: "" },
        })
      );
      bindSortables();
      syncHidden();
    });

    $("#sf_shop_menu_add_group").on("click", function () {
      $tree.append(
        groupRowHtml({
          id: uid(),
          type: "group",
          label: { en: "New group", ar: "" },
          children: [],
        })
      );
      bindSortables();
      syncHidden();
    });

    $("#storefront_settings_form").on("submit", function () {
      syncHidden();
    });

    render();
  }

  $(function () {
    initShopMenuBuilder();
  });
})(jQuery);
