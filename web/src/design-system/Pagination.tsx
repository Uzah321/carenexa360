interface PaginationProps {
  currentPage: number;
  lastPage: number;
  onPageChange: (page: number) => void;
}

export function Pagination({ currentPage, lastPage, onPageChange }: PaginationProps) {
  if (lastPage <= 1) return null;

  return (
    <div className="mt-4 flex items-center justify-between rounded-2xl border border-line bg-white px-4 py-2.5">
      <button
        type="button"
        disabled={currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
        className="rounded-full px-3 py-1.5 text-sm font-medium text-inksoft transition-colors duration-150 hover:bg-paper disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
      >
        Previous
      </button>
      <span className="text-sm text-inksoft">
        Page {currentPage} of {lastPage}
      </span>
      <button
        type="button"
        disabled={currentPage >= lastPage}
        onClick={() => onPageChange(currentPage + 1)}
        className="rounded-full px-3 py-1.5 text-sm font-medium text-inksoft transition-colors duration-150 hover:bg-paper disabled:opacity-40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal"
      >
        Next
      </button>
    </div>
  );
}
