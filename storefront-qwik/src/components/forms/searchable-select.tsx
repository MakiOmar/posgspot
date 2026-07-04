import { $, component$, useSignal, type QRL } from "@builder.io/qwik";
import { tStatic, useI18n } from "~/lib/i18n/context";

export type SelectOption = {
  value: string;
  label: string;
  searchText?: string;
  meta?: string;
};

type Props = {
  id: string;
  options: SelectOption[];
  value: string;
  placeholder?: string;
  required?: boolean;
  disabled?: boolean;
  displayLabel?: string;
  onChange$: QRL<(value: string, option: SelectOption | undefined) => void>;
};

function filterOptions(options: SelectOption[], query: string): SelectOption[] {
  const q = query.trim().toLowerCase();
  if (!q) {
    return options;
  }
  return options.filter((o) => {
    const hay = `${o.label} ${o.searchText ?? ""} ${o.value}`.toLowerCase();
    return hay.includes(q);
  });
}

export const SearchableSelect = component$<Props>((props) => {
  const { locale } = useI18n();
  const open = useSignal(false);
  const query = useSignal("");
  const activeId = useSignal("");

  const selected =
    props.options.find((o) => o.value === props.value) ??
    props.options.find((o) => o.label === props.displayLabel);

  const display =
    props.displayLabel || selected?.label || props.placeholder || tStatic(locale, "forms.select");
  const visibleOptions = filterOptions(props.options, query.value);

  const close$ = $(() => {
    open.value = false;
    query.value = "";
    activeId.value = "";
  });

  const select$ = $((value: string) => {
    const option = props.options.find((o) => o.value === value);
    props.onChange$(value, option);
    close$();
  });

  return (
    <div class={`searchable-select${open.value ? " searchable-select--open" : ""}`}>
      <button
        type="button"
        id={props.id}
        class="searchable-select__trigger"
        disabled={props.disabled}
        aria-haspopup="listbox"
        aria-expanded={open.value}
        onClick$={() => {
          if (!props.disabled) {
            open.value = !open.value;
            if (open.value) {
              query.value = "";
            }
          }
        }}
      >
        <span class="searchable-select__value">{display}</span>
        <span class="searchable-select__chevron" aria-hidden="true">
          ▾
        </span>
      </button>

      {open.value ? (
        <div class="searchable-select__panel" role="listbox">
          <input
            type="search"
            class="searchable-select__search"
            placeholder={tStatic(locale, "forms.search")}
            autoComplete="off"
            value={query.value}
            onInput$={(_, el) => {
              query.value = el.value;
            }}
            onKeyDown$={(e) => {
              const items = filterOptions(props.options, query.value);
              if (e.key === "Escape") {
                close$();
                return;
              }
              if (e.key === "ArrowDown" && items.length) {
                e.preventDefault();
                const idx = items.findIndex((i) => i.value === activeId.value);
                const next = items[Math.min(idx + 1, items.length - 1)];
                activeId.value = next?.value ?? items[0].value;
              }
              if (e.key === "ArrowUp" && items.length) {
                e.preventDefault();
                const idx = items.findIndex((i) => i.value === activeId.value);
                const prev = items[Math.max(idx - 1, 0)];
                activeId.value = prev?.value ?? items[0].value;
              }
              if (e.key === "Enter" && activeId.value) {
                e.preventDefault();
                select$(activeId.value);
              }
            }}
          />
          <ul class="searchable-select__list">
            {visibleOptions.map((option) => (
              <li key={option.value}>
                <button
                  type="button"
                  role="option"
                  class={`searchable-select__option${
                    option.value === props.value ? " searchable-select__option--selected" : ""
                  }${option.value === activeId.value ? " searchable-select__option--active" : ""}`}
                  onClick$={() => select$(option.value)}
                >
                  {option.meta ? <span class="searchable-select__meta">{option.meta}</span> : null}
                  <span>{option.label}</span>
                </button>
              </li>
            ))}
            {visibleOptions.length === 0 ? (
              <li class="searchable-select__empty">{tStatic(locale, "forms.noMatches")}</li>
            ) : null}
          </ul>
        </div>
      ) : null}

      {props.required ? (
        <input
          type="text"
          tabIndex={-1}
          aria-hidden="true"
          class="searchable-select__validator"
          value={props.value}
          required
          onChange$={() => {}}
        />
      ) : null}
    </div>
  );
});
