import { useForm, router } from '@inertiajs/react';
import { useEffect, useState, type FormEvent, type ChangeEvent } from 'react';
import { Card, tableClasses } from './Primitives';

type AttachmentRow = {
  id: string;
  entity_type: string;
  entity_id: string;
  name: string;
  mime: string;
  size: number;
  at: string;
};

type AttachmentPanelProps = {
  entityType: string;
  entityId: string;
  initialAttachments?: AttachmentRow[];
  locale?: 'en' | 'ar';
};

export default function AttachmentPanel({
  entityType,
  entityId,
  initialAttachments = [],
  locale = 'en',
}: AttachmentPanelProps) {
  const [attachmentsList, setAttachmentsList] = useState<AttachmentRow[]>(initialAttachments);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  const isAr = locale === 'ar';

  const { data, setData, post, processing, errors, reset } = useForm({
    entity_type: entityType,
    entity_id: entityId,
    file: null as File | null,
  });

  async function loadAttachments() {
    if (!entityType || !entityId) return;

    const params = new URLSearchParams({
      entity_type: entityType,
      entity_id: entityId,
    });

    const response = await fetch(`/attachments?${params.toString()}`, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) return;

    const payload = await response.json() as { attachments?: AttachmentRow[] };
    setAttachmentsList(payload.attachments ?? []);
  }

  useEffect(() => {
    void loadAttachments();
  }, [entityType, entityId]);

  function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setSelectedFile(file);
      setData('file', file);
    }
  }

  function handleUpload(e: FormEvent<HTMLFormElement>) {
    e.preventDefault();
    if (!data.file) return;

    post('/attachments', {
      preserveScroll: true,
      onSuccess: () => {
        reset('file');
        setSelectedFile(null);
        void loadAttachments();
      },
    });
  }

  function handleDelete(id: string) {
    if (confirm(isAr ? 'هل أنت متاكد من حذف هذا المرفق؟' : 'Are you sure you want to delete this attachment?')) {
      router.delete(`/attachments/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
          setAttachmentsList((prev) => prev.filter((item) => item.id !== id));
        },
      });
    }
  }

  function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  return (
    <Card className="p-5 border-[var(--border)] bg-[var(--surface)]">
      <div className="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-4">
        <div className="flex items-center gap-2">
          <svg className="size-4 text-[var(--primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
          </svg>
          <h4 className="m-0 text-sm font-bold text-[var(--text-primary)]">
            {isAr ? 'المرفقات والملفات' : 'Attachments & Documents'}
          </h4>
        </div>
        <span className="text-xs text-[var(--text-muted)] font-mono">
          {attachmentsList.length} {isAr ? 'ملف' : 'files'}
        </span>
      </div>

      {/* Upload Form */}
      <form onSubmit={handleUpload} className="mb-4 flex flex-wrap items-center gap-3">
        <label className="flex-1 min-w-[200px] cursor-pointer rounded-xl border border-dashed border-[var(--border)] bg-[var(--background)] px-3.5 py-2 text-xs text-[var(--text-secondary)] hover:border-[var(--primary)] hover:text-[var(--text-primary)] transition-colors">
          <input
            type="file"
            onChange={handleFileChange}
            className="hidden"
            accept=".pdf,.png,.jpg,.jpeg,.webp,.txt,.csv,.xlsx,.docx"
          />
          <span className="truncate block">
            {selectedFile ? selectedFile.name : (isAr ? 'اختر ملفاً لإرفاقه (PDF, PNG, JPG, XLSX... Max 10MB)' : 'Choose file to attach (PDF, PNG, JPG, XLSX... Max 10MB)')}
          </span>
        </label>

        <button
          type="submit"
          disabled={processing || !data.file}
          className="inline-flex items-center gap-1.5 rounded-xl bg-[var(--primary)] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[var(--primary-hover)] transition-all disabled:opacity-50 cursor-pointer"
        >
          <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>{isAr ? 'رفع المرفق' : 'Upload File'}</span>
        </button>
      </form>

      {errors.file ? <p className="m-0 mb-3 text-xs font-semibold text-[var(--danger)]">{errors.file}</p> : null}

      {/* Attachments List */}
      {attachmentsList.length === 0 ? (
        <p className="m-0 text-xs text-[var(--text-muted)] italic py-2">
          {isAr ? 'لا توجد مرفقات حالياً لهذا العنصر.' : 'No attachments associated with this record yet.'}
        </p>
      ) : (
        <div className="overflow-x-auto">
          <table className={tableClasses.table}>
            <thead>
              <tr className="border-b border-[var(--border)] bg-[var(--background)]/50">
                <th className={tableClasses.th}>{isAr ? 'اسم الملف' : 'File Name'}</th>
                <th className={tableClasses.th}>{isAr ? 'الحجم' : 'Size'}</th>
                <th className={tableClasses.th}>{isAr ? 'تاريخ الرفع' : 'Uploaded At'}</th>
                <th className={`${tableClasses.th} text-end`}>{isAr ? 'الإجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody>
              {attachmentsList.map((att) => (
                <tr key={att.id} className="border-b border-[var(--border)] hover:bg-[var(--background)]/50 transition-colors">
                  <td className={tableClasses.td}>
                    <div className="flex items-center gap-2">
                      <svg className="size-4 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                      </svg>
                      <span className="font-semibold text-xs text-[var(--text-primary)] truncate max-w-[200px]" title={att.name}>
                        {att.name}
                      </span>
                    </div>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="font-mono text-xs text-[var(--text-muted)]">{formatSize(att.size)}</span>
                  </td>
                  <td className={tableClasses.td}>
                    <span className="text-xs text-[var(--text-muted)]">{new Date(att.at).toLocaleDateString()}</span>
                  </td>
                  <td className={`${tableClasses.td} text-end`}>
                    <div className="flex items-center justify-end gap-2">
                      <a
                        href={`/attachments/${att.id}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 rounded-lg border border-blue-500/20 bg-blue-500/10 px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 transition-all no-underline"
                      >
                        <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>{isAr ? 'تنزيل' : 'Download'}</span>
                      </a>
                      <button
                        type="button"
                        onClick={() => handleDelete(att.id)}
                        className="inline-flex items-center gap-1 rounded-lg border border-red-500/20 bg-red-500/10 px-2.5 py-1 text-xs font-bold text-red-600 dark:text-red-400 hover:bg-red-500/20 transition-all cursor-pointer"
                      >
                        <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>{isAr ? 'حذف' : 'Delete'}</span>
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
