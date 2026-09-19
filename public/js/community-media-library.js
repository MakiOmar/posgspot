/**
 * Community post cover + gallery ↔ storefront media library.
 * Mounts on #community_media_field.
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
    var root = document.getElementById("community_media_field");
    if (!root) {
      return;
    }

    var mediaUrl = root.getAttribute("data-media-url") || "";
    var uploadUrl = root.getAttribute("data-upload-url") || "";
    var modal = document.getElementById("community_library_modal");
    var grid = document.getElementById("community_library_grid");
    var statusEl = document.getElementById("community_library_status");
    var pageEl = document.getElementById("community_library_page");
    var prevBtn = document.getElementById("community_library_prev");
    var nextBtn = document.getElementById("community_library_next");
    var qInput = document.getElementById("community_library_q");
    var fileInput = document.getElementById("community_library_file");
    var coverPreview = document.getElementById("community_cover_preview");
    var coverPathInput = document.getElementById("community_cover_path");
    var galleryPending = document.getElementById("community_gallery_pending");

    var mode = "cover"; // cover | gallery
    var page = 1;
    var lastPage = 1;
    var gallerySelected = {};

    function setStatus(msg) {
      if (statusEl) {
        statusEl.textContent = msg || "";
      }
    }

    function openModal(nextMode) {
      mode = nextMode || "cover";
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

    function setCover(item) {
      if (!item) {
        return;
      }
      if (coverPathInput) {
        coverPathInput.value = item.path || item.image || "";
      }
      if (coverPreview) {
        coverPreview.innerHTML =
          '<img src="' +
          (item.image_url || item.url || "") +
          '" alt="" style="max-height:120px;border-radius:6px;">' +
          '<p class="help-block" style="margin-top:6px;">Selected from media library</p>';
      }
      var remove = document.getElementById("community_remove_cover");
      if (remove) {
        remove.checked = false;
      }
      closeModal();
    }

    function renderGalleryPending() {
      if (!galleryPending) {
        return;
      }
      galleryPending.innerHTML = "";
      Object.keys(gallerySelected).forEach(function (id) {
        var item = gallerySelected[id];
        var col = document.createElement("div");
        col.className = "col-xs-4 col-sm-3 col-md-2";
        col.style.marginBottom = "8px";
        col.innerHTML =
          '<div class="img-thumbnail" style="position:relative;display:inline-block;">' +
          '<span class="badge bg-red community-lib-remove" data-id="' +
          id +
          '" style="cursor:pointer;"><i class="fas fa-times"></i></span>' +
          '<img src="' +
          (item.image_url || "") +
          '" alt="" style="max-width:80px;max-height:80px;display:block;">' +
          '<input type="hidden" name="gallery_library_paths[]" value="' +
          (item.path || "") +
          '">' +
          "</div>";
        galleryPending.appendChild(col);
      });
    }

    function pickGallery(item) {
      if (!item || !item.id) {
        return;
      }
      gallerySelected[String(item.id)] = {
        id: item.id,
        path: item.path || item.image || "",
        image_url: item.image_url || item.url || "",
      };
      renderGalleryPending();
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
        headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        credentials: "same-origin",
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.success) {
            setStatus((data && data.msg) || "Failed to load library.");
            return;
          }
          lastPage = (data.meta && data.meta.last_page) || 1;
          if (pageEl) {
            pageEl.textContent = "Page " + page + " / " + lastPage;
          }
          if (prevBtn) {
            prevBtn.disabled = page <= 1;
          }
          if (nextBtn) {
            nextBtn.disabled = page >= lastPage;
          }
          setStatus((data.items || []).length ? "" : "No images found.");
          (data.items || []).forEach(function (item) {
            var card = document.createElement("button");
            card.type = "button";
            card.className = "community-lib-card";
            card.innerHTML =
              '<img src="' +
              (item.image_url || "") +
              '" alt="">' +
              '<span class="community-lib-name">' +
              (item.original_name || item.path || "") +
              "</span>";
            card.addEventListener("click", function () {
              if (mode === "cover") {
                setCover(item);
              } else {
                pickGallery(item);
              }
            });
            if (grid) {
              grid.appendChild(card);
            }
          });
        })
        .catch(function () {
          setStatus("Failed to load library.");
        });
    }

    var coverBtn = document.getElementById("community_cover_library_btn");
    if (coverBtn) {
      coverBtn.addEventListener("click", function () {
        openModal("cover");
      });
    }
    var galleryBtn = document.getElementById("community_gallery_library_btn");
    if (galleryBtn) {
      galleryBtn.addEventListener("click", function () {
        openModal("gallery");
      });
    }
    var closeBtn = document.getElementById("community_library_close");
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
    var searchBtn = document.getElementById("community_library_search");
    if (searchBtn) {
      searchBtn.addEventListener("click", function () {
        load(1);
      });
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
    if (galleryPending) {
      galleryPending.addEventListener("click", function (e) {
        var btn = e.target.closest(".community-lib-remove");
        if (!btn) {
          return;
        }
        delete gallerySelected[btn.getAttribute("data-id")];
        renderGalleryPending();
      });
    }

    var uploadBtn = document.getElementById("community_library_upload");
    if (uploadBtn && fileInput) {
      uploadBtn.addEventListener("click", function () {
        fileInput.click();
      });
      fileInput.addEventListener("change", function () {
        if (!fileInput.files || !fileInput.files[0] || !uploadUrl) {
          return;
        }
        var fd = new FormData();
        fd.append("image", fileInput.files[0]);
        fd.append("_token", csrf());
        setStatus("Uploading…");
        fetch(uploadUrl, {
          method: "POST",
          body: fd,
          credentials: "same-origin",
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            fileInput.value = "";
            if (!data || !data.success) {
              setStatus((data && data.msg) || "Upload failed.");
              return;
            }
            var item = {
              id: data.media_id,
              path: data.image,
              image: data.image,
              image_url: data.image_url,
              url: data.image_url,
              original_name: "Uploaded",
            };
            if (mode === "cover") {
              setCover(item);
            } else {
              pickGallery(item);
              load(1);
            }
          })
          .catch(function () {
            setStatus("Upload failed.");
            fileInput.value = "";
          });
      });
    }
  });
})();
