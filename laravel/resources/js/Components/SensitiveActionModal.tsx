import { useState } from 'react';
import { Button, Modal } from './Primitives';
import { getDictionary } from '../lib/i18n';

export interface SensitiveActionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: (payload: { confirm_action: string; reason?: string }) => void;
  title?: string;
  message?: string;
  confirmCode: string;
  reasonRequired?: boolean;
  isProcessing?: boolean;
  locale?: string;
}

export default function SensitiveActionModal({
  isOpen,
  onClose,
  onConfirm,
  title,
  message,
  confirmCode,
  reasonRequired = false,
  isProcessing = false,
  locale = 'en',
}: SensitiveActionModalProps) {
  const [reason, setReason] = useState('');
  const [touched, setTouched] = useState(false);

  const dict = getDictionary(locale);
  const sensitiveDict = dict.app.sensitiveActions;

  const resolvedTitle = title || sensitiveDict.modalTitle;
  const resolvedMessage = message || sensitiveDict.confirmPrompt;

  const isReasonValid = !reasonRequired || reason.trim().length >= 3;
  const showReasonError = reasonRequired && touched && reason.trim().length < 3;

  const handleSubmit = (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    setTouched(true);

    if (!isReasonValid || isProcessing) {
      return;
    }

    onConfirm({
      confirm_action: confirmCode,
      reason: reason.trim() ? reason.trim() : undefined,
    });
  };

  const handleClose = () => {
    if (isProcessing) return;
    setReason('');
    setTouched(false);
    onClose();
  };

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={resolvedTitle}>
      <form onSubmit={handleSubmit} className="space-y-4">
        <p className="text-xs text-[var(--text-secondary)] leading-relaxed">{resolvedMessage}</p>

        <div className="rounded-xl border border-[var(--border)] bg-[var(--background)] p-3">
          <span className="block text-[10px] font-bold uppercase tracking-wider text-[var(--text-muted)] mb-1">
            {sensitiveDict.confirmationCodeLabel}
          </span>
          <span className="font-mono text-xs font-bold text-blue-600 dark:text-blue-400">
            {confirmCode}
          </span>
        </div>

        {reasonRequired ? (
          <div>
            <label className="block text-xs font-bold text-[var(--text-primary)] mb-1">
              {sensitiveDict.reasonLabel}{' '}
              <span className="text-red-500">*</span>
            </label>
            <textarea
              value={reason}
              onChange={(e) => {
                setReason(e.target.value);
                if (!touched) setTouched(true);
              }}
              placeholder={sensitiveDict.reasonPlaceholder}
              rows={3}
              maxLength={1000}
              required
              className="w-full rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3 text-xs text-[var(--text-primary)] focus:border-[var(--primary)] focus:outline-none transition-colors"
            />
            {showReasonError ? (
              <p className="mt-1 text-[11px] text-red-500 font-medium">{sensitiveDict.reasonRequiredError}</p>
            ) : null}
          </div>
        ) : null}

        <div className="flex justify-end gap-2 pt-2 border-t border-[var(--border)]">
          <Button
            type="button"
            variant="secondary"
            onClick={handleClose}
            disabled={isProcessing}
            title={sensitiveDict.cancelButton}
            aria-label={sensitiveDict.cancelButton}
          >
            {sensitiveDict.cancelButton}
          </Button>

          <Button
            type="submit"
            variant="primary"
            disabled={isProcessing || !isReasonValid}
            title={sensitiveDict.confirmButton}
            aria-label={sensitiveDict.confirmButton}
          >
            {isProcessing ? sensitiveDict.processing : sensitiveDict.confirmButton}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
