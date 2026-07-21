/**
 * Storefront homepage section builder (Vue 3 island).
 * Expects #storefront-homepage-builder with data-sections / data-types / data-categories / data-save-url / data-upload-url.
 */
(function () {
  function csrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute("content") : "";
  }

  function uid(prefix) {
    return prefix + "_" + Math.random().toString(36).slice(2, 10);
  }

  function emptyLocale() {
    return { en: "", ar: "" };
  }

  function defaultPromoBanner() {
    return {
      logo: { image: null, url: "", image_url: "" },
      top_title: emptyLocale(),
      main_title: emptyLocale(),
      top_title_color: "#111111",
      main_title_color: "#111111",
      background_color: "#f5a623",
      border_radius: 16,
      border_color: "#000000",
      border_thickness: 0,
      min_height: 180,
      image: {
        image: null,
        url: "",
        image_url: "",
        position: { top: "-12%", right: "2%", bottom: "auto", left: "auto", width: "42%" },
      },
      button: {
        label: { en: "Shop Now", ar: "تسوق الآن" },
        link: "/products",
        background_color: "#ffffff",
        text_color: "#111111",
        border_radius: 4,
        show_arrow: true,
        arrow_color: "#f5c518",
        position: { top: "auto", right: "5%", bottom: "18%", left: "auto", width: "auto" },
      },
    };
  }

  function defaultSettings(type) {
    switch (type) {
      case "hero_slider":
        return { slides: [] };
      case "promo_tiles":
        return { tiles: [] };
      case "video":
        return { source: "self", url: "", poster: "", title: emptyLocale() };
      case "trust_badges":
        return { items: [] };
      case "promo_banners":
        return { max: 12 };
      case "promo_banner":
        return defaultPromoBanner();
      case "featured_products":
        return { per_page: 8 };
      case "top_categories":
        return { limit: 8 };
      case "category_shelves":
        return { limit: 6, products_per_shelf: 6 };
      case "category_shelf":
        return { category_id: null, products_per_shelf: 6 };
      case "brand_slider":
        return { limit: 24 };
      case "bestsellers":
        return { per_page: 6, in_stock_only: true, style: "grid" };
      case "recently_viewed":
        return { limit: 8 };
      default:
        return {};
    }
  }

  function mount() {
    var el = document.getElementById("storefront-homepage-builder");
    if (!el || typeof Vue === "undefined") {
      return;
    }

    var sections = [];
    var types = [];
    var categories = [];
    try {
      sections = JSON.parse(el.getAttribute("data-sections") || "[]");
    } catch (e) {
      sections = [];
    }
    if (Array.isArray(sections)) {
      sections.forEach(function (s) {
        if (!s.layout_width) {
          s.layout_width = "boxed";
        }
        if (s.type === "trust_badges" && s.settings && Array.isArray(s.settings.items)) {
          s.settings.items.forEach(function (item) {
            if (!item.icon_kind) {
              item.icon_kind = "image";
            }
            if (!item.icon_color) {
              item.icon_color = "#f5a623";
            }
            if (typeof item.svg_markup !== "string") {
              item.svg_markup = "";
            }
          });
        }
      });
    }
    try {
      types = JSON.parse(el.getAttribute("data-types") || "[]");
    } catch (e) {
      types = [];
    }
    try {
      categories = JSON.parse(el.getAttribute("data-categories") || "[]");
    } catch (e) {
      categories = [];
    }

    var saveUrl = el.getAttribute("data-save-url") || "";
    var uploadUrl = el.getAttribute("data-upload-url") || "";

    Vue.createApp({
      data: function () {
        return {
          sections: Array.isArray(sections) ? sections : [],
          types: Array.isArray(types) ? types : [],
          categories: Array.isArray(categories) ? categories : [],
          selectedType: types[0] ? types[0].type : "",
          openId: null,
          saving: false,
          uploading: false,
          message: "",
          error: "",
        };
      },
      computed: {
        typeLabelMap: function () {
          var map = {};
          this.types.forEach(function (t) {
            map[t.type] = t.label;
          });
          return map;
        },
        insertableTypes: function () {
          var counts = {};
          this.sections.forEach(function (s) {
            counts[s.type] = (counts[s.type] || 0) + 1;
          });
          return this.types.filter(function (t) {
            if (t.max_instances == null) {
              return true;
            }
            return (counts[t.type] || 0) < t.max_instances;
          });
        },
      },
      mounted: function () {
        var self = this;
        if (typeof Sortable === "undefined") {
          return;
        }
        Sortable.create(this.$refs.list, {
          handle: ".sf-hp-drag",
          animation: 150,
          onEnd: function (evt) {
            if (evt.oldIndex === evt.newIndex) {
              return;
            }
            var moved = self.sections.splice(evt.oldIndex, 1)[0];
            self.sections.splice(evt.newIndex, 0, moved);
          },
        });
      },
      methods: {
        labelFor: function (type) {
          return this.typeLabelMap[type] || type;
        },
        insertSection: function () {
          var type = this.selectedType;
          if (!type) {
            return;
          }
          var allowed = this.insertableTypes.some(function (t) {
            return t.type === type;
          });
          if (!allowed) {
            this.error = "That section type is already at its max instances.";
            return;
          }
          var section = {
            id: uid("sec"),
            type: type,
            enabled: true,
            layout_width: "boxed",
            settings: defaultSettings(type),
          };
          this.sections.push(section);
          this.openId = section.id;
          this.error = "";
          var next = this.insertableTypes.find(function (t) {
            return t.type === type;
          });
          if (!next && this.insertableTypes[0]) {
            this.selectedType = this.insertableTypes[0].type;
          }
        },
        removeSection: function (index) {
          if (!confirm("Remove this section?")) {
            return;
          }
          var removed = this.sections.splice(index, 1)[0];
          if (removed && this.openId === removed.id) {
            this.openId = null;
          }
        },
        toggleOpen: function (id) {
          this.openId = this.openId === id ? null : id;
        },
        addSlide: function (section) {
          if (!section.settings.slides) {
            section.settings.slides = [];
          }
          section.settings.slides.push({
            id: uid("slide"),
            image: null,
            url: "",
            href: "/products",
            kicker: emptyLocale(),
            title: emptyLocale(),
            image_url: null,
          });
        },
        removeSlide: function (section, index) {
          section.settings.slides.splice(index, 1);
        },
        addTile: function (section) {
          if (!section.settings.tiles) {
            section.settings.tiles = [];
          }
          section.settings.tiles.push({
            id: uid("tile"),
            image: null,
            url: "",
            href: "/products",
            label: emptyLocale(),
            image_url: null,
          });
        },
        removeTile: function (section, index) {
          section.settings.tiles.splice(index, 1);
        },
        addTrustBadge: function (section) {
          if (!section.settings.items) {
            section.settings.items = [];
          }
          if (section.settings.items.length >= 8) {
            this.error = "Maximum of 8 trust badge items.";
            return;
          }
          section.settings.items.push({
            id: uid("badge"),
            icon_kind: "image",
            icon_color: "#f5a623",
            image: null,
            url: "",
            image_url: "",
            svg_markup: "",
            title: emptyLocale(),
            description: emptyLocale(),
          });
          this.error = "";
        },
        removeTrustBadge: function (section, index) {
          section.settings.items.splice(index, 1);
        },
        duplicateTrustBadge: function (section, index) {
          if (!section.settings.items) {
            section.settings.items = [];
          }
          if (section.settings.items.length >= 8) {
            this.error = "Maximum of 8 trust badge items.";
            return;
          }
          var src = section.settings.items[index];
          if (!src) {
            return;
          }
          var copy = {
            id: uid("badge"),
            icon_kind: src.icon_kind === "svg" ? "svg" : "image",
            icon_color: src.icon_color || "#f5a623",
            image: src.image || null,
            url: src.url || "",
            image_url: src.image_url || src.url || "",
            svg_markup: src.svg_markup || "",
            title: {
              en: (src.title && src.title.en) || "",
              ar: (src.title && src.title.ar) || "",
            },
            description: {
              en: (src.description && src.description.en) || "",
              ar: (src.description && src.description.ar) || "",
            },
          };
          section.settings.items.splice(index + 1, 0, copy);
          this.error = "";
        },
        loadTrustBadgeSvgFromUrl: function (item) {
          var self = this;
          var url = (item.url || "").trim();
          if (!url) {
            self.error = "Enter an SVG URL first.";
            return;
          }
          self.uploading = true;
          fetch(url, { credentials: "omit" })
            .then(function (res) {
              if (!res.ok) {
                throw new Error("fetch failed");
              }
              return res.text();
            })
            .then(function (text) {
              self.uploading = false;
              if (!/<svg\b/i.test(text)) {
                self.error = "URL did not return SVG markup.";
                return;
              }
              item.icon_kind = "svg";
              item.svg_markup = text;
              item.image = null;
              self.message = "SVG loaded — adjust color to preview.";
              self.error = "";
            })
            .catch(function () {
              self.uploading = false;
              self.error = "Could not load SVG (CORS or invalid URL). Upload the file instead.";
            });
        },
        clearBannerImage: function (section) {
          if (!section.settings.image) {
            return;
          }
          section.settings.image.image = null;
          section.settings.image.url = "";
          section.settings.image.image_url = "";
        },
        clearBannerLogo: function (section) {
          if (!section.settings.logo) {
            return;
          }
          section.settings.logo.image = null;
          section.settings.logo.url = "";
          section.settings.logo.image_url = "";
        },
        uploadMedia: function (item, opts) {
          var self = this;
          var options = opts || {};
          var acceptSvg = !!options.acceptSvg;
          var input = document.createElement("input");
          input.type = "file";
          input.accept = acceptSvg ? "image/*,.svg,image/svg+xml" : "image/*";
          input.onchange = function () {
            var file = input.files && input.files[0];
            if (!file) {
              return;
            }
            var body = new FormData();
            body.append("image", file);
            self.uploading = true;
            fetch(uploadUrl, {
              method: "POST",
              headers: {
                "X-CSRF-TOKEN": csrf(),
                Accept: "application/json",
              },
              body: body,
              credentials: "same-origin",
            })
              .then(function (res) {
                return res.json();
              })
              .then(function (json) {
                self.uploading = false;
                if (!json.success) {
                  self.error = json.msg || "Upload failed";
                  return;
                }
                item.image = json.image;
                item.url = "";
                item.image_url = json.image_url;
                if (json.icon_kind === "svg" && json.svg_markup) {
                  item.icon_kind = "svg";
                  item.svg_markup = json.svg_markup;
                } else if (options.forceImageKind) {
                  item.icon_kind = "image";
                  item.svg_markup = "";
                }
                self.message = "File uploaded.";
                self.error = "";
              })
              .catch(function () {
                self.uploading = false;
                self.error = "Upload failed";
              });
          };
          input.click();
        },
        save: function () {
          var self = this;
          self.saving = true;
          self.message = "";
          self.error = "";
          fetch(saveUrl, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
              "X-CSRF-TOKEN": csrf(),
            },
            body: JSON.stringify({ sections: self.sections }),
            credentials: "same-origin",
          })
            .then(function (res) {
              return res.json();
            })
            .then(function (json) {
              self.saving = false;
              if (!json.success) {
                self.error = json.msg || "Save failed";
                return;
              }
              if (Array.isArray(json.sections)) {
                self.sections = json.sections;
              }
              self.message = json.msg || "Saved.";
              if (typeof toastr !== "undefined") {
                toastr.success(self.message);
              }
            })
            .catch(function () {
              self.saving = false;
              self.error = "Save failed";
            });
        },
      },
      template: `
        <div class="sf-hp-builder">
          <div class="alert alert-info">
            Insert, reorder, and configure homepage sections. Catalog sections still pull products/categories/brands from their existing POS fields.
          </div>
          <div class="sf-hp-toolbar">
            <select v-model="selectedType" class="form-control" style="max-width:280px;display:inline-block;">
              <option v-for="t in insertableTypes" :key="t.type" :value="t.type">{{ t.label }}</option>
            </select>
            <button type="button" class="btn btn-primary" @click="insertSection" :disabled="!insertableTypes.length">Insert section</button>
            <button type="button" class="btn btn-success" @click="save" :disabled="saving">
              {{ saving ? 'Saving…' : 'Save homepage' }}
            </button>
            <span v-if="uploading" class="text-muted">Uploading…</span>
          </div>
          <p v-if="message" class="text-success">{{ message }}</p>
          <p v-if="error" class="text-danger">{{ error }}</p>
          <div ref="list" class="sf-hp-list">
            <div v-for="(section, index) in sections" :key="section.id" class="sf-hp-card box box-solid">
              <div class="box-header with-border sf-hp-card__head">
                <span class="sf-hp-drag" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <strong>{{ labelFor(section.type) }}</strong>
                <label class="sf-hp-enable">
                  <input type="checkbox" v-model="section.enabled" /> Enabled
                </label>
                <div class="box-tools pull-right">
                  <button type="button" class="btn btn-box-tool" @click="toggleOpen(section.id)">
                    <i :class="openId === section.id ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"></i>
                  </button>
                  <button type="button" class="btn btn-box-tool text-danger" @click="removeSection(index)">
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </div>
              <div v-show="openId === section.id" class="box-body">
                <div class="form-group sf-hp-layout-width">
                  <label>Section width</label>
                  <select class="form-control" v-model="section.layout_width" style="max-width:280px;">
                    <option value="boxed">Boxed (align with site content)</option>
                    <option value="full">Full viewport (edge-to-edge with side margins)</option>
                  </select>
                </div>
                <template v-if="section.type === 'hero_slider'">
                  <button type="button" class="btn btn-default btn-sm" @click="addSlide(section)">Add slide</button>
                  <div v-for="(slide, si) in section.settings.slides" :key="slide.id" class="sf-hp-media-row">
                    <img v-if="slide.image_url || slide.url" :src="slide.image_url || slide.url" alt="" class="sf-hp-thumb" />
                    <div class="sf-hp-media-fields">
                      <input class="form-control input-sm" v-model="slide.url" placeholder="Image URL" :disabled="!!slide.image" />
                      <input class="form-control input-sm" v-model="slide.href" placeholder="Link path e.g. /products" />
                      <input class="form-control input-sm" v-model="slide.kicker.en" placeholder="Kicker (EN)" />
                      <input class="form-control input-sm" v-model="slide.kicker.ar" placeholder="Kicker (AR)" dir="rtl" />
                      <input class="form-control input-sm" v-model="slide.title.en" placeholder="Title (EN)" />
                      <input class="form-control input-sm" v-model="slide.title.ar" placeholder="Title (AR)" dir="rtl" />
                      <button type="button" class="btn btn-default btn-xs" @click="uploadMedia(slide)">Upload image</button>
                      <button type="button" class="btn btn-danger btn-xs" @click="removeSlide(section, si)">Remove</button>
                    </div>
                  </div>
                </template>

                <template v-else-if="section.type === 'promo_tiles'">
                  <button type="button" class="btn btn-default btn-sm" @click="addTile(section)">Add tile</button>
                  <div v-for="(tile, ti) in section.settings.tiles" :key="tile.id" class="sf-hp-media-row">
                    <img v-if="tile.image_url || tile.url" :src="tile.image_url || tile.url" alt="" class="sf-hp-thumb" />
                    <div class="sf-hp-media-fields">
                      <input class="form-control input-sm" v-model="tile.url" placeholder="Image URL" :disabled="!!tile.image" />
                      <input class="form-control input-sm" v-model="tile.href" placeholder="Link path" />
                      <input class="form-control input-sm" v-model="tile.label.en" placeholder="Label (EN)" />
                      <input class="form-control input-sm" v-model="tile.label.ar" placeholder="Label (AR)" dir="rtl" />
                      <button type="button" class="btn btn-default btn-xs" @click="uploadMedia(tile)">Upload image</button>
                      <button type="button" class="btn btn-danger btn-xs" @click="removeTile(section, ti)">Remove</button>
                    </div>
                  </div>
                </template>

                <template v-else-if="section.type === 'video'">
                  <div class="form-group">
                    <label>Source</label>
                    <select class="form-control" v-model="section.settings.source">
                      <option value="self">Self-hosted (MP4 / direct URL)</option>
                      <option value="youtube">YouTube</option>
                      <option value="vimeo">Vimeo</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label v-if="section.settings.source === 'youtube'">YouTube URL or video ID</label>
                    <label v-else-if="section.settings.source === 'vimeo'">Vimeo URL or video ID</label>
                    <label v-else>Video file URL</label>
                    <input class="form-control" v-model="section.settings.url"
                      :placeholder="section.settings.source === 'youtube'
                        ? 'https://www.youtube.com/watch?v=…'
                        : (section.settings.source === 'vimeo'
                          ? 'https://vimeo.com/123456789'
                          : 'https://…/video.mp4')" />
                    <p class="help-block" v-if="section.settings.source === 'youtube'">Accepts watch, youtu.be, Shorts, or embed links.</p>
                    <p class="help-block" v-else-if="section.settings.source === 'vimeo'">Accepts vimeo.com or player.vimeo.com links.</p>
                  </div>
                  <div class="form-group" v-if="section.settings.source === 'self' || !section.settings.source">
                    <label>Poster URL (optional)</label>
                    <input class="form-control" v-model="section.settings.poster" placeholder="Preview image for self-hosted video" />
                  </div>
                  <div class="form-group">
                    <label>Title (EN)</label>
                    <input class="form-control" v-model="section.settings.title.en" />
                  </div>
                  <div class="form-group">
                    <label>Title (AR)</label>
                    <input class="form-control" v-model="section.settings.title.ar" dir="rtl" />
                  </div>
                </template>

                <template v-else-if="section.type === 'trust_badges'">
                  <p class="help-block">Row of trust / service items (icon + title + description). Use SVG icons for recolorable line icons.</p>
                  <button type="button" class="btn btn-default btn-sm" @click="addTrustBadge(section)">Add item</button>
                  <div v-for="(item, bi) in section.settings.items" :key="item.id" class="sf-hp-media-row sf-hp-trust-item">
                    <div class="sf-hp-trust-preview">
                      <div
                        v-if="item.icon_kind === 'svg' && item.svg_markup"
                        class="sf-hp-svg-preview"
                        :style="{ color: item.icon_color || '#f5a623' }"
                        v-html="item.svg_markup"
                      ></div>
                      <img v-else-if="item.image_url || item.url" :src="item.image_url || item.url" alt="" class="sf-hp-thumb" />
                      <div v-else class="sf-hp-thumb sf-hp-thumb--empty">No icon</div>
                    </div>
                    <div class="sf-hp-media-fields">
                      <select class="form-control input-sm" v-model="item.icon_kind">
                        <option value="image">Image (PNG/JPG/WebP)</option>
                        <option value="svg">SVG (inline markup, recolorable)</option>
                      </select>
                      <template v-if="item.icon_kind === 'svg'">
                        <input class="form-control input-sm" v-model="item.url" placeholder="SVG URL (optional)" />
                        <button type="button" class="btn btn-default btn-xs" @click="loadTrustBadgeSvgFromUrl(item)">Load SVG from URL</button>
                        <button type="button" class="btn btn-default btn-xs" @click="uploadMedia(item, { acceptSvg: true })">Upload SVG</button>
                        <label class="sf-hp-color-label">Icon color
                          <input type="color" v-model="item.icon_color" />
                          <input class="form-control input-sm" style="max-width:120px;display:inline-block;" v-model="item.icon_color" />
                        </label>
                        <textarea class="form-control input-sm" rows="3" v-model="item.svg_markup" placeholder="Or paste SVG markup here — preview updates live"></textarea>
                      </template>
                      <template v-else>
                        <input class="form-control input-sm" v-model="item.url" placeholder="Icon image URL" :disabled="!!item.image" />
                        <button type="button" class="btn btn-default btn-xs" @click="uploadMedia(item, { forceImageKind: true })">Upload image</button>
                      </template>
                      <input class="form-control input-sm" v-model="item.title.en" placeholder="Title (EN)" />
                      <input class="form-control input-sm" v-model="item.title.ar" placeholder="Title (AR)" dir="rtl" />
                      <input class="form-control input-sm" v-model="item.description.en" placeholder="Description (EN)" />
                      <input class="form-control input-sm" v-model="item.description.ar" placeholder="Description (AR)" dir="rtl" />
                      <button type="button" class="btn btn-default btn-xs" @click="duplicateTrustBadge(section, bi)" :disabled="section.settings.items.length >= 8">Duplicate</button>
                      <button type="button" class="btn btn-danger btn-xs" @click="removeTrustBadge(section, bi)">Remove</button>
                    </div>
                  </div>
                </template>

                <template v-else-if="section.type === 'promo_banners'">
                  <p class="help-block">Legacy: uses banners from the Banners tab with placement = home. Prefer inserting “Promo banner” sections instead.</p>
                  <div class="form-group">
                    <label>Max banners</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.max" />
                  </div>
                </template>

                <template v-else-if="section.type === 'promo_banner'">
                  <p class="help-block">Compositional banner: logo + titles + background/border + absolutely positioned product image + Shop Now button. Use CSS lengths like <code>-12%</code>, <code>24px</code>, or <code>auto</code>.</p>

                  <fieldset class="sf-hp-fieldset">
                    <legend>Logo</legend>
                    <div class="sf-hp-media-row">
                      <img v-if="section.settings.logo.image_url || section.settings.logo.url" :src="section.settings.logo.image_url || section.settings.logo.url" alt="" class="sf-hp-thumb" />
                      <div class="sf-hp-media-fields">
                        <input class="form-control input-sm" v-model="section.settings.logo.url" placeholder="Logo image URL" :disabled="!!section.settings.logo.image" />
                        <button type="button" class="btn btn-default btn-xs" @click="uploadMedia(section.settings.logo)">Upload logo</button>
                        <button type="button" class="btn btn-default btn-xs" v-if="section.settings.logo.image || section.settings.logo.url" @click="clearBannerLogo(section)">Clear logo</button>
                      </div>
                    </div>
                  </fieldset>

                  <fieldset class="sf-hp-fieldset">
                    <legend>Titles</legend>
                    <div class="form-group">
                      <label>Top title (EN)</label>
                      <input class="form-control input-sm" v-model="section.settings.top_title.en" />
                    </div>
                    <div class="form-group">
                      <label>Top title (AR)</label>
                      <input class="form-control input-sm" v-model="section.settings.top_title.ar" dir="rtl" />
                    </div>
                    <div class="form-group">
                      <label>Top title color</label>
                      <input type="color" v-model="section.settings.top_title_color" />
                      <input class="form-control input-sm" style="max-width:120px;display:inline-block;margin-left:8px;" v-model="section.settings.top_title_color" />
                    </div>
                    <div class="form-group">
                      <label>Main title (EN)</label>
                      <input class="form-control input-sm" v-model="section.settings.main_title.en" />
                    </div>
                    <div class="form-group">
                      <label>Main title (AR)</label>
                      <input class="form-control input-sm" v-model="section.settings.main_title.ar" dir="rtl" />
                    </div>
                    <div class="form-group">
                      <label>Main title color</label>
                      <input type="color" v-model="section.settings.main_title_color" />
                      <input class="form-control input-sm" style="max-width:120px;display:inline-block;margin-left:8px;" v-model="section.settings.main_title_color" />
                    </div>
                  </fieldset>

                  <fieldset class="sf-hp-fieldset">
                    <legend>Background &amp; border</legend>
                    <div class="form-group">
                      <label>Background color</label>
                      <input type="color" v-model="section.settings.background_color" />
                      <input class="form-control input-sm" style="max-width:120px;display:inline-block;margin-left:8px;" v-model="section.settings.background_color" />
                    </div>
                    <div class="row">
                      <div class="col-sm-3">
                        <label>Border radius (px)</label>
                        <input type="number" min="0" max="64" class="form-control input-sm" v-model.number="section.settings.border_radius" />
                      </div>
                      <div class="col-sm-3">
                        <label>Border thickness (px)</label>
                        <input type="number" min="0" max="24" class="form-control input-sm" v-model.number="section.settings.border_thickness" />
                      </div>
                      <div class="col-sm-3">
                        <label>Border color</label>
                        <input type="color" class="form-control input-sm" v-model="section.settings.border_color" />
                      </div>
                      <div class="col-sm-3">
                        <label>Min height (px)</label>
                        <input type="number" min="80" max="640" class="form-control input-sm" v-model.number="section.settings.min_height" />
                      </div>
                    </div>
                  </fieldset>

                  <fieldset class="sf-hp-fieldset">
                    <legend>Product image (absolute)</legend>
                    <div class="sf-hp-media-row">
                      <img v-if="section.settings.image.image_url || section.settings.image.url" :src="section.settings.image.image_url || section.settings.image.url" alt="" class="sf-hp-thumb" />
                      <div class="sf-hp-media-fields">
                        <input class="form-control input-sm" v-model="section.settings.image.url" placeholder="Product image URL" :disabled="!!section.settings.image.image" />
                        <button type="button" class="btn btn-default btn-xs" @click="uploadMedia(section.settings.image)">Upload image</button>
                        <button type="button" class="btn btn-default btn-xs" v-if="section.settings.image.image || section.settings.image.url" @click="clearBannerImage(section)">Clear image</button>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-sm-2"><label>Top</label><input class="form-control input-sm" v-model="section.settings.image.position.top" placeholder="auto" /></div>
                      <div class="col-sm-2"><label>Right</label><input class="form-control input-sm" v-model="section.settings.image.position.right" placeholder="auto" /></div>
                      <div class="col-sm-2"><label>Bottom</label><input class="form-control input-sm" v-model="section.settings.image.position.bottom" placeholder="auto" /></div>
                      <div class="col-sm-2"><label>Left</label><input class="form-control input-sm" v-model="section.settings.image.position.left" placeholder="auto" /></div>
                      <div class="col-sm-2"><label>Width</label><input class="form-control input-sm" v-model="section.settings.image.position.width" placeholder="42%" /></div>
                    </div>
                  </fieldset>

                  <fieldset class="sf-hp-fieldset">
                    <legend>Shop Now button</legend>
                    <div class="form-group">
                      <label>Label (EN)</label>
                      <input class="form-control input-sm" v-model="section.settings.button.label.en" />
                    </div>
                    <div class="form-group">
                      <label>Label (AR)</label>
                      <input class="form-control input-sm" v-model="section.settings.button.label.ar" dir="rtl" />
                    </div>
                    <div class="form-group">
                      <label>Link</label>
                      <input class="form-control input-sm" v-model="section.settings.button.link" placeholder="/products or https://…" />
                    </div>
                    <div class="row">
                      <div class="col-sm-3">
                        <label>Background</label>
                        <input type="color" class="form-control input-sm" v-model="section.settings.button.background_color" />
                      </div>
                      <div class="col-sm-3">
                        <label>Text color</label>
                        <input type="color" class="form-control input-sm" v-model="section.settings.button.text_color" />
                      </div>
                      <div class="col-sm-3">
                        <label>Arrow color</label>
                        <input type="color" class="form-control input-sm" v-model="section.settings.button.arrow_color" />
                      </div>
                      <div class="col-sm-3">
                        <label>Radius (px)</label>
                        <input type="number" min="0" max="64" class="form-control input-sm" v-model.number="section.settings.button.border_radius" />
                      </div>
                    </div>
                    <label style="margin-top:8px;display:block;">
                      <input type="checkbox" v-model="section.settings.button.show_arrow" /> Show arrow
                    </label>
                    <div class="row" style="margin-top:8px;">
                      <div class="col-sm-2"><label>Top</label><input class="form-control input-sm" v-model="section.settings.button.position.top" /></div>
                      <div class="col-sm-2"><label>Right</label><input class="form-control input-sm" v-model="section.settings.button.position.right" /></div>
                      <div class="col-sm-2"><label>Bottom</label><input class="form-control input-sm" v-model="section.settings.button.position.bottom" /></div>
                      <div class="col-sm-2"><label>Left</label><input class="form-control input-sm" v-model="section.settings.button.position.left" /></div>
                    </div>
                  </fieldset>
                </template>

                <template v-else-if="section.type === 'featured_products'">
                  <div class="form-group">
                    <label>Products per page</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.per_page" />
                  </div>
                  <p class="help-block">Products marked “Storefront featured” in POS.</p>
                </template>

                <template v-else-if="section.type === 'top_categories'">
                  <div class="form-group">
                    <label>Limit</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.limit" />
                  </div>
                </template>

                <template v-else-if="section.type === 'category_shelves'">
                  <div class="form-group">
                    <label>Max shelves</label>
                    <input type="number" min="1" max="12" class="form-control" v-model.number="section.settings.limit" />
                  </div>
                  <div class="form-group">
                    <label>Products per shelf</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.products_per_shelf" />
                  </div>
                  <p class="help-block">Legacy: shelves enabled on product category edit. Prefer inserting “Category shelf” sections instead.</p>
                </template>

                <template v-else-if="section.type === 'category_shelf'">
                  <div class="form-group">
                    <label>Category</label>
                    <select class="form-control" v-model.number="section.settings.category_id">
                      <option :value="null">— Select category —</option>
                      <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                        {{ cat.parent_id ? '— ' : '' }}{{ cat.name }}
                      </option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Products per shelf</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.products_per_shelf" />
                  </div>
                  <p class="help-block">Renders like a category shelf (banner fields from the category + product grid). Insert multiple sections for multiple categories.</p>
                </template>

                <template v-else-if="section.type === 'brand_slider'">
                  <div class="form-group">
                    <label>Limit</label>
                    <input type="number" min="1" max="48" class="form-control" v-model.number="section.settings.limit" />
                  </div>
                </template>

                <template v-else-if="section.type === 'bestsellers'">
                  <div class="form-group">
                    <label>Style</label>
                    <select class="form-control" v-model="section.settings.style">
                      <option value="grid">Grid cards (default)</option>
                      <option value="horizontal">Horizontal cards (image + details)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label>Products per page</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.per_page" />
                  </div>
                  <label>
                    <input type="checkbox" v-model="section.settings.in_stock_only" /> In stock only
                  </label>
                </template>

                <template v-else-if="section.type === 'recently_viewed'">
                  <div class="form-group">
                    <label>Limit</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.limit" />
                  </div>
                </template>

                <p v-else class="text-muted">No extra settings for this section.</p>
              </div>
            </div>
          </div>
          <p v-if="!sections.length" class="text-muted">No sections yet — insert one above.</p>
        </div>
      `,
    }).mount(el);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mount);
  } else {
    mount();
  }
})();
