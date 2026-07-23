/**
 * Product gallery ↔ storefront media library picker.
 * Mounts on #product_gallery_field (create/edit product forms).
 */
(function () {
  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute("content")) {
      return meta.getAttribute("content");
    }
    var input = document.querySelector('input[name="_token"]');
    return input ? input.value : "";
  }

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var root = document.getElementById("product_gallery_field");
    if (!root) {
      return;
    }

    var mediaUrl = root.getAttribute("data-media-url") || "";
    var uploadUrl = root.getAttribute("data-upload-url") || "";
    var modal = document.getElementById("product_gallery_library_modal");
    var grid = document.getElementById("product_gallery_library_grid");
    var statusEl = document.getElementById("product_gallery_library_status");
    var pendingEl = document.getElementById("product_gallery_library_pending");
    var pageEl = document.getElementById("product_gallery_library_page");
    var prevBtn = document.getElementById("product_gallery_library_prev");
    var nextBtn = document.getElementById("product_gallery_library_next");
    var qInput = document.getElementById("product_gallery_library_q");
    var fileInput = document.getElementById("product_gallery_library_file");

    var page = 1;
    var lastPage = 1;
    var selected = {};

    function setStatus(msg) {
      if (statusEl) {
        statusEl.textContent = msg || "";
      }
    }

    function openModal() {
      if (!modal) {
        return;
      }
      modal.hidden = false;
      load(1);
    }

    function closeModal() {
      if (modal) {
        modal.hidden = true;
      }
    }

    function renderPending() {
      if (!pendingEl) {
        return;
      }
      pendingEl.innerHTML = "";
      Object.keys(selected).forEach(function (id) {
        var item = selected[id];
        var col = document.createElement("div");
        col.className = "col-xs-4 col-sm-3 col-md-2";
        col.style.marginBottom = "8px";
        col.innerHTML =
          '<div class="img-thumbnail" style="position:relative;display:inline-block;">' +
          '<span class="badge bg-red product-gallery-lib-remove" data-id="' +
          id +
          '" style="cursor:pointer;"><i class="fas fa-times"></i></span>' +
          '<img src="' +
          (item.image_url || "") +
          '" alt="" style="max-width:80px;max-height:80px;display:block;">' +
          '<input type="hidden" name="product_gallery_library_ids[]" value="' +
          id +
          '">' +
          "</div>";
        pendingEl.appendChild(col);
      });
    }

    function pick(item) {
      if (!item || !item.id) {
        return;
      }
      selected[String(item.id)] = {
        id: item.id,
        image_url: item.image_url || item.url || "",
      };
      renderPending();
      closeModal();
    }

    function load(p) {
      if (!mediaUrl) {
        setStatus("Media library URL missing — refresh the page.");
        return;
      }
      page = p || 1;
      var params = new URLSearchParams();
      params.set("page", String(page));
      params.set("per_page", "24");
      params.set("kind", "image");
      if (qInput && qInput.value.trim()) {
        params.set("q", qInput.value.trim());
      }
      setStatus("Loading…");
      if (grid) {
        grid.innerHTML = "";
      }
      fetch(mediaUrl + "?" + params.toString(), {
        method: "GET",
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (json) {
          if (!json.success) {
            setStatus(json.msg || "Could not load media library.");
            return;
          }
          setStatus("");
          var items = Array.isArray(json.items) ? json.items : [];
          lastPage = (json.meta && json.meta.last_page) || 1;
          page = (json.meta && json.meta.current_page) || page;
          if (pageEl) {
            pageEl.textContent = "Page " + page + " / " + lastPage;
          }
          if (prevBtn) {
            prevBtn.disabled = page <= 1;
          }
          if (nextBtn) {
            nextBtn.disabled = page >= lastPage;
          }
          if (!grid) {
            return;
          }
          if (!items.length) {
            grid.innerHTML = '<p class="text-muted">No images yet — upload one.</p>';
            return;
          }
          items.forEach(function (m) {
            var btn = document.createElement("button");
            btn.type = "button";
            btn.className = "product-gallery-lib-card";
            btn.innerHTML =
              (m.image_url
                ? '<img src="' + m.image_url + '" alt="">'
                : '<span class="text-muted">—</span>') +
              '<span class="product-gallery-lib-name">' +
              (m.original_name || m.path || "") +
              "</span>";
            btn.addEventListener("click", function () {
              pick(m);
            });
            grid.appendChild(btn);
          });
        })
        .catch(function () {
          setStatus("Could not load media library.");
        });
    }

    function uploadNew() {
      if (!uploadUrl || !fileInput) {
        return;
      }
      fileInput.value = "";
      fileInput.click();
    }

    if (fileInput) {
      fileInput.addEventListener("change", function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) {
          return;
        }
        var fd = new FormData();
        fd.append("image", file);
        setStatus("Uploading…");
        fetch(uploadUrl, {
          method: "POST",
          headers: {
            "X-CSRF-TOKEN": csrf(),
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          body: fd,
          credentials: "same-origin",
        })
          .then(function (res) {
            return res.json();
          })
          .then(function (json) {
            if (!json.success) {
              setStatus(json.msg || "Upload failed.");
              return;
            }
            pick({
              id: json.media_id,
              image_url: json.image_url,
            });
            setStatus(json.deduped ? "Reused existing library file." : "Uploaded.");
          })
          .catch(function () {
            setStatus("Upload failed.");
          });
      });
    }

    var openBtn = document.getElementById("product_gallery_library_btn");
    if (openBtn) {
      openBtn.addEventListener("click", openModal);
    }
    var closeBtn = document.getElementById("product_gallery_library_close");
    if (closeBtn) {
      closeBtn.addEventListener("click", closeModal);
    }
    if (modal) {
      modal.addEventListener("click", function (e) {
        if (e.target === modal) {
          closeModal();
        }
      });
    }
    var searchBtn = document.getElementById("product_gallery_library_search");
    if (searchBtn) {
      searchBtn.addEventListener("click", function () {
        load(1);
      });
    }
    if (qInput) {
      qInput.addEventListener("keyup", function (e) {
        if (e.key === "Enter") {
          e.preventDefault();
          load(1);
        }
      });
    }
    var uploadBtn = document.getElementById("product_gallery_library_upload");
    if (uploadBtn) {
      uploadBtn.addEventListener("click", uploadNew);
    }
    if (prevBtn) {
      prevBtn.addEventListener("click", function () {
        if (page > 1) {
          load(page - 1);
        }
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener("click", function () {
        if (page < lastPage) {
          load(page + 1);
        }
      });
    }
    if (pendingEl) {
      pendingEl.addEventListener("click", function (e) {
        var badge = e.target.closest(".product-gallery-lib-remove");
        if (!badge) {
          return;
        }
        var id = badge.getAttribute("data-id");
        if (id && selected[id]) {
          delete selected[id];
          renderPending();
        }
      });
    }
  });
})();
