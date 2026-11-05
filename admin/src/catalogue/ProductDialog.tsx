import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Icon } from '../ui/components';
import './product-dialog.css';

export default function ProductDialog({
  open,
  title,
  onClose,
  children,
}: {
  open: boolean;
  title: string;
  onClose: () => void;
  children: ReactNode;
}) {
  const dialog = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  useEffect(() => {
    const element = dialog.current;
    if (!open || !element) return;
    const trigger = document.activeElement as HTMLElement | null;
    const previousOverflow = document.body.style.overflow;
    element.showModal();
    document.body.style.overflow = 'hidden';
    return () => {
      element.close();
      document.body.style.overflow = previousOverflow;
      if (trigger?.isConnected) trigger.focus();
    };
  }, [open]);
  return (
    <dialog
      ref={dialog}
      className="product-dialog"
      aria-labelledby={titleId}
      onCancel={(e) => {
        e.preventDefault();
        onClose();
      }}
    >
      <div className="product-dialog-heading">
        <div>
          <span className="product-dialog-symbol">
            <Icon name="box" />
          </span>
          <div>
            <h2 id={titleId}>{title}</h2>
            <p>Details, photos and pricing in one workspace</p>
          </div>
        </div>
        <button
          type="button"
          className="icon-button"
          aria-label="Close product form"
          autoFocus
          onClick={onClose}
        >
          <Icon name="close" />
        </button>
      </div>
      <div className="product-dialog-content">{children}</div>
    </dialog>
  );
}
