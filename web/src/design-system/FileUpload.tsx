import { useRef, useState } from "react";
import { Button } from "./form/Button";

interface FileUploadProps {
  onSelect: (file: File) => void;
  accept?: string;
}

export function FileUpload({ onSelect, accept }: FileUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [fileName, setFileName] = useState<string | null>(null);

  return (
    <div className="flex items-center gap-3 rounded-xl border border-dashed border-line bg-paper px-4 py-3">
      <Button
        type="button"
        variant="secondary"
        onClick={() => inputRef.current?.click()}
      >
        Choose file
      </Button>
      <span className="truncate text-sm text-inksoft">
        {fileName ?? "No file selected"}
      </span>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) {
            setFileName(file.name);
            onSelect(file);
          }
        }}
      />
    </div>
  );
}
