import { useEffect, useRef, type ReactNode } from "react";
import { Bold, FilePlus2, Highlighter, Italic, List, Underline } from "lucide-react";
import { richTextIsEmpty, richTextToHtml, sanitizeRichText } from "../../lib/richText";

interface RichTextEditorProps {
  id: string;
  value: string;
  onChange: (html: string) => void;
  /** Starter text the template button inserts — omit to hide the button. */
  template?: string;
  placeholder?: string;
  required?: boolean;
  "aria-label"?: string;
}

function ToolbarButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      // Keep focus (and the selection) in the editor while clicking.
      onMouseDown={(e) => e.preventDefault()}
      onClick={onClick}
      className="rounded p-1.5 text-inksoft transition-colors duration-150 hover:bg-paper hover:text-ink"
    >
      {children}
    </button>
  );
}

/**
 * A small formatting editor: bold, italic, underline, highlight and bullet
 * lists. Emits sanitised HTML; an empty editor emits "".
 */
export function RichTextEditor({ id, value, onChange, template, placeholder, required, ...rest }: RichTextEditorProps) {
  const ref = useRef<HTMLDivElement>(null);
  // The last HTML this editor emitted — lets us skip resetting the DOM (and
  // losing the caret) when the value coming back in is our own.
  const emitted = useRef<string | null>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el || value === emitted.current) return;
    el.innerHTML = richTextToHtml(value);
    emitted.current = value;
  }, [value]);

  function emit() {
    const el = ref.current;
    if (!el) return;
    const html = richTextIsEmpty(el.innerHTML) ? "" : sanitizeRichText(el.innerHTML);
    emitted.current = html;
    onChange(html);
  }

  function run(command: string, arg?: string) {
    ref.current?.focus();
    document.execCommand("styleWithCSS", false, "false");
    document.execCommand(command, false, arg);
    emit();
  }

  function insertTemplate() {
    const el = ref.current;
    if (!el || !template) return;
    const html = richTextIsEmpty(el.innerHTML) ? richTextToHtml(template) : `${el.innerHTML}<br>${richTextToHtml(template)}`;
    el.innerHTML = html;
    emit();
  }

  return (
    <div className="rounded-lg border border-line shadow-sm transition-colors duration-150 focus-within:border-teal focus-within:ring-1 focus-within:ring-teal">
      <div className="flex items-center gap-0.5 border-b border-line bg-paper/60 px-1.5 py-1" role="toolbar" aria-label="Formatting">
        <ToolbarButton label="Bold" onClick={() => run("bold")}>
          <Bold className="h-3.5 w-3.5" />
        </ToolbarButton>
        <ToolbarButton label="Italic" onClick={() => run("italic")}>
          <Italic className="h-3.5 w-3.5" />
        </ToolbarButton>
        <ToolbarButton label="Underline" onClick={() => run("underline")}>
          <Underline className="h-3.5 w-3.5" />
        </ToolbarButton>
        <ToolbarButton
          label="Highlight"
          onClick={() => {
            ref.current?.focus();
            document.execCommand("styleWithCSS", false, "true");
            document.execCommand("hiliteColor", false, "#fde68a");
            emit();
          }}
        >
          <Highlighter className="h-3.5 w-3.5" />
        </ToolbarButton>
        <ToolbarButton label="Bulleted list" onClick={() => run("insertUnorderedList")}>
          <List className="h-3.5 w-3.5" />
        </ToolbarButton>
        {template && (
          <>
            <span className="mx-1 h-4 w-px bg-line" aria-hidden />
            <ToolbarButton label="Insert template" onClick={insertTemplate}>
              <FilePlus2 className="h-3.5 w-3.5" />
            </ToolbarButton>
          </>
        )}
      </div>
      <div
        ref={ref}
        id={id}
        role="textbox"
        aria-multiline="true"
        aria-required={required || undefined}
        aria-label={rest["aria-label"]}
        contentEditable
        suppressContentEditableWarning
        data-placeholder={placeholder}
        onInput={emit}
        onBlur={emit}
        className="rich-text min-h-24 px-3 py-2 text-sm text-ink focus:outline-none empty:before:text-inksoft/70 empty:before:content-[attr(data-placeholder)]"
      />
    </div>
  );
}

/** Renders saved rich text (or legacy plain text) safely. */
export function RichTextView({ value, className = "" }: { value: string | null | undefined; className?: string }) {
  if (!value || richTextIsEmpty(value)) return null;
  return <div className={`rich-text text-sm text-ink ${className}`} dangerouslySetInnerHTML={{ __html: richTextToHtml(value) }} />;
}
