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

  function defaultSettings(type) {
    switch (type) {
      case "hero_slider":
        return { slides: [] };
      case "promo_tiles":
        return { tiles: [] };
      case "video":
        return { url: "", poster: "", title: emptyLocale() };
      case "promo_banners":
        return { max: 12 };
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
        return { per_page: 6, in_stock_only: true };
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
        uploadMedia: function (item) {
          var self = this;
          var input = document.createElement("input");
          input.type = "file";
          input.accept = "image/*";
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
                self.message = "Image uploaded.";
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
                    <label>Video URL</label>
                    <input class="form-control" v-model="section.settings.url" />
                  </div>
                  <div class="form-group">
                    <label>Poster URL</label>
                    <input class="form-control" v-model="section.settings.poster" />
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

                <template v-else-if="section.type === 'promo_banners'">
                  <p class="help-block">Uses banners from the Banners tab with placement = home.</p>
                  <div class="form-group">
                    <label>Max banners</label>
                    <input type="number" min="1" max="24" class="form-control" v-model.number="section.settings.max" />
                  </div>
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
