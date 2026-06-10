import { useState, useEffect, useCallback, useMemo } from 'react';
import PropTypes from 'prop-types';
import { useTranslation } from 'react-i18next';
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useSensor,
  useSensors,
  closestCenter,
  useDraggable,
  useDroppable,
} from '@dnd-kit/core';
import { catalogApi } from '../../api/catalog';
import DeliverableFormModal from './DeliverableFormModal';
import ServiceFormModal from './ServiceFormModal';
import PackageFormModal from './PackageFormModal';

// ─── Tab type constants ────────────────────────────────────────────────────────

const TAB_DELIVERABLES = 'deliverables';
const TAB_SERVICES = 'services';
const TAB_PACKAGES = 'packages';

// Sentinel id used for the "Uncategorized" droppable container.
const UNCATEGORIZED_ID = 'uncategorized';

// ─── Icons (inline SVG) ───────────────────────────────────────────────────────

function IconDeliverable({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
    </svg>
  );
}
IconDeliverable.propTypes = { className: PropTypes.string };

function IconService({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
  );
}
IconService.propTypes = { className: PropTypes.string };

function IconPackage({ className = 'w-4 h-4' }) {
  return (
    <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
    </svg>
  );
}
IconPackage.propTypes = { className: PropTypes.string };

function IconChevron({ open, className = 'w-4 h-4' }) {
  return (
    <svg
      className={`${className} transition-transform ${open ? 'rotate-90' : ''}`}
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      viewBox="0 0 24 24"
    >
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
    </svg>
  );
}
IconChevron.propTypes = { open: PropTypes.bool, className: PropTypes.string };

function IconDragHandle({ className = 'w-4 h-4' }) {
  return (
    <svg
      className={className}
      fill="currentColor"
      viewBox="0 0 20 20"
      aria-hidden="true"
    >
      <circle cx="7" cy="4" r="1.4" />
      <circle cx="13" cy="4" r="1.4" />
      <circle cx="7" cy="10" r="1.4" />
      <circle cx="13" cy="10" r="1.4" />
      <circle cx="7" cy="16" r="1.4" />
      <circle cx="13" cy="16" r="1.4" />
    </svg>
  );
}
IconDragHandle.propTypes = { className: PropTypes.string };

// ─── Tab button ───────────────────────────────────────────────────────────────

function TabButton({ active, onClick, icon, label, count }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${active
          ? 'border-blue-600 text-blue-600'
          : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
        }`}
    >
      {icon}
      <span>{label}</span>
      <span
        className={`inline-flex items-center justify-center min-w-[1.5rem] h-5 px-1.5 rounded-full text-xs font-semibold ${active ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'
          }`}
      >
        {count}
      </span>
    </button>
  );
}

TabButton.propTypes = {
  active: PropTypes.bool.isRequired,
  onClick: PropTypes.func.isRequired,
  icon: PropTypes.node.isRequired,
  label: PropTypes.string.isRequired,
  count: PropTypes.number.isRequired,
};

// ─── Deliverable row (inside service accordion) ───────────────────────────────
// Wraps the row in a useDraggable so it can be dragged into another accordion.

function DeliverableRow({ deliverable, lang, onEdit, onDelete }) {
  const { t } = useTranslation('common');
  const name = lang === 'es' ? deliverable.name_es : deliverable.name_en;

  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: `deliverable-${deliverable.id}`,
    data: { deliverableId: deliverable.id },
  });

  return (
    <div
      ref={setNodeRef}
      className={`group/row flex items-center justify-between px-4 py-2.5 hover:bg-slate-50 border-t border-slate-100 ${isDragging ? 'opacity-30' : ''}`}
    >
      <div className="flex items-center gap-3 flex-1 min-w-0">
        {/* Drag handle — only this element is bound to dnd-kit listeners */}
        <button
          type="button"
          ref={undefined}
          {...listeners}
          {...attributes}
          aria-label={t('catalog.drag_handle')}
          className="cursor-grab active:cursor-grabbing p-1 -ml-1 rounded text-slate-300 hover:text-slate-500 opacity-0 group-hover/row:opacity-100 transition-opacity touch-none"
          // Prevent the button from submitting a parent form if any
          onClick={(e) => e.preventDefault()}
        >
          <IconDragHandle className="w-4 h-4" />
        </button>
        <p className="text-sm text-slate-800 truncate">{name}</p>
        {deliverable.is_monthly && (
          <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-600/20 shrink-0">
            {t('catalog.monthly')}
          </span>
        )}
      </div>
      <div className="flex items-center gap-1 shrink-0">
        <div className="flex items-center gap-1 text-slate-400 mr-2">
          <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span className="text-sm text-slate-500">
            {parseFloat(deliverable.estimated_hours ?? 0).toFixed(2)}h
          </span>
        </div>
        <button
          type="button"
          onClick={() => onEdit(deliverable)}
          className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 opacity-0 group-hover/row:opacity-100 transition-opacity"
          aria-label={t('common.edit')}
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
          </svg>
        </button>
        <button
          type="button"
          onClick={() => onDelete(deliverable)}
          className="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 opacity-0 group-hover/row:opacity-100 transition-opacity"
          aria-label={t('common.delete')}
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
          </svg>
        </button>
      </div>
    </div>
  );
}

DeliverableRow.propTypes = {
  deliverable: PropTypes.shape({
    id: PropTypes.number.isRequired,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    estimated_hours: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    is_monthly: PropTypes.bool,
  }).isRequired,
  lang: PropTypes.string.isRequired,
  onEdit: PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
};

// ─── Service accordion (deliverables tab) ─────────────────────────────────────
// The body of the accordion is a droppable area for deliverables.

function ServiceAccordion({
  service,
  lang,
  onEditDeliverable,
  onDeleteDeliverable,
  droppableId,
  variant = 'default',
}) {
  const [open, setOpen] = useState(true);
  const deliverables = Array.isArray(service.children) ? service.children : [];
  const name = lang === 'es'
    ? (service.name_es ?? service.label)
    : (service.name_en ?? service.label);

  const { setNodeRef, isOver } = useDroppable({ id: droppableId });

  // Border-left color: amber for uncategorized, blue for normal.
  const borderLeftClass =
    variant === 'uncategorized'
      ? 'border-l-4 border-l-amber-400'
      : 'border-l-4 border-l-blue-500';

  return (
    <div
      className={`bg-white rounded-xl border border-slate-200 ${borderLeftClass} shadow-sm overflow-hidden transition-colors ${isOver ? 'ring-2 ring-blue-300 ring-offset-1' : ''}`}
    >
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors text-left"
      >
        <IconChevron open={open} className="w-4 h-4 text-slate-500" />
        <h3 className="text-sm font-semibold text-slate-800 flex-1 truncate">{name}</h3>
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
          {deliverables.length}
        </span>
      </button>
      {open && (
        <div
          ref={setNodeRef}
          className={`bg-white min-h-[2.5rem] ${isOver ? 'bg-blue-50' : ''} transition-colors`}
        >
          {deliverables.length === 0 && (
            <div className="px-4 py-3 text-xs text-slate-400 border-t border-slate-100 italic">
              —
            </div>
          )}
          {deliverables.map((d) => (
            <DeliverableRow
              key={d.id}
              deliverable={d}
              lang={lang}
              onEdit={onEditDeliverable}
              onDelete={onDeleteDeliverable}
            />
          ))}
        </div>
      )}
    </div>
  );
}

ServiceAccordion.propTypes = {
  service: PropTypes.shape({
    id: PropTypes.oneOfType([PropTypes.number, PropTypes.string]).isRequired,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    label: PropTypes.string,
    children: PropTypes.array,
  }).isRequired,
  lang: PropTypes.string.isRequired,
  onEditDeliverable: PropTypes.func.isRequired,
  onDeleteDeliverable: PropTypes.func.isRequired,
  droppableId: PropTypes.string.isRequired,
  variant: PropTypes.oneOf(['default', 'uncategorized']),
};

// ─── Drag overlay row (ghost shown while dragging) ────────────────────────────

function DragOverlayRow({ deliverable, lang }) {
  const { t } = useTranslation('common');
  const name = lang === 'es' ? deliverable.name_es : deliverable.name_en;
  return (
    <div className="flex items-center gap-3 px-4 py-2.5 bg-white rounded-lg border border-slate-200 shadow-lg ring-1 ring-blue-200 max-w-md cursor-grabbing">
      <IconDragHandle className="w-4 h-4 text-slate-400" />
      <p className="text-sm text-slate-800 truncate">{name}</p>
      {deliverable.is_monthly && (
        <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-600/20 shrink-0">
          {t('catalog.monthly')}
        </span>
      )}
    </div>
  );
}

DragOverlayRow.propTypes = {
  deliverable: PropTypes.shape({
    id: PropTypes.number.isRequired,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    is_monthly: PropTypes.bool,
  }).isRequired,
  lang: PropTypes.string.isRequired,
};

// ─── Service card (services tab) ──────────────────────────────────────────────

function ServiceCard({ service, lang, onEdit, onDelete }) {
  const { t } = useTranslation('common');
  const name = lang === 'es' ? service.name_es : service.name_en;
  const deliverables = Array.isArray(service.children) ? service.children : [];

  return (
    <div className="group bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all p-5">
      <div className="flex items-start justify-between gap-3 mb-3">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-base font-semibold text-slate-800 truncate">{name}</h3>
            {service.service_type && (
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20">
                {t(`catalog.service_type.${service.service_type}`, {
                  defaultValue: service.service_type,
                })}
              </span>
            )}
          </div>
          <p className="mt-1 text-sm text-slate-500">
            {service.total_hours ?? 0} {t('catalog.hours_total')}
          </p>
        </div>
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            type="button"
            onClick={() => onEdit(service)}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            aria-label={t('common.edit')}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => onDelete(service)}
            className="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
            aria-label={t('common.delete')}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
          </button>
        </div>
      </div>
      {deliverables.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {deliverables.map((d) => (
            <span
              key={d.id}
              className="inline-flex items-center px-2 py-0.5 rounded-md text-xs text-slate-600 bg-slate-100"
            >
              {lang === 'es' ? d.name_es : d.name_en}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}

ServiceCard.propTypes = {
  service: PropTypes.shape({
    id: PropTypes.number.isRequired,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    service_type: PropTypes.string,
    total_hours: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    children: PropTypes.array,
  }).isRequired,
  lang: PropTypes.string.isRequired,
  onEdit: PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
};

// ─── Package card (packages tab) ──────────────────────────────────────────────

function PackageCard({ bundle, lang, onEdit, onDelete }) {
  const { t } = useTranslation('common');
  const name = lang === 'es' ? bundle.name_es : bundle.name_en;
  const childServices = Array.isArray(bundle.children) ? bundle.children : [];

  return (
    <div className="group bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all p-5">
      <div className="flex items-start justify-between gap-3 mb-3">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <h3 className="text-base font-semibold text-slate-800 truncate">{name}</h3>
            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20">
              {t('catalog.tabs.packages')}
            </span>
          </div>
          <p className="mt-1 text-sm text-slate-500">
            {bundle.total_hours ?? 0} {t('catalog.hours_total')}
          </p>
        </div>
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            type="button"
            onClick={() => onEdit(bundle)}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
            aria-label={t('common.edit')}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
            </svg>
          </button>
          <button
            type="button"
            onClick={() => onDelete(bundle)}
            className="p-1.5 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 transition-colors"
            aria-label={t('common.delete')}
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
          </button>
        </div>
      </div>
      {childServices.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {childServices.map((s) => (
            <span
              key={s.id}
              className="inline-flex items-center px-2 py-0.5 rounded-md text-xs text-slate-600 bg-slate-100"
            >
              {lang === 'es' ? s.name_es : s.name_en}
            </span>
          ))}
        </div>
      )}
    </div>
  );
}

PackageCard.propTypes = {
  bundle: PropTypes.shape({
    id: PropTypes.number.isRequired,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    total_hours: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
    children: PropTypes.array,
  }).isRequired,
  lang: PropTypes.string.isRequired,
  onEdit: PropTypes.func.isRequired,
  onDelete: PropTypes.func.isRequired,
};

// ─── Delete service confirmation modal ────────────────────────────────────────
// Inline modal that asks how to handle child deliverables when removing a service.

function DeleteServiceModal({ service, lang, onCancel, onConfirm }) {
  const { t } = useTranslation('common');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const name = lang === 'es' ? service.name_es : service.name_en;
  const childrenCount = Array.isArray(service.children) ? service.children.length : 0;
  const hasChildren = childrenCount > 0;

  async function handle(cascade) {
    setSubmitting(true);
    setError('');
    try {
      await onConfirm(cascade);
    } catch (err) {
      const msgKey = err?.response?.data?.message;
      setError(
        msgKey ? t(msgKey, { defaultValue: msgKey }) : t('catalog.delete_error')
      );
      setSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <h2 className="text-lg font-semibold text-slate-800">
          {t('catalog.delete_service_title', { name })}
        </h2>
        <p className="mt-3 text-sm text-slate-600">
          {hasChildren
            ? t('catalog.delete_service_has_children', { count: childrenCount })
            : t('catalog.delete_service_no_children')}
        </p>

        {error && (
          <p className="mt-3 text-sm text-red-600">{error}</p>
        )}

        <div className="mt-6 flex flex-wrap gap-2 justify-end">
          <button
            type="button"
            onClick={onCancel}
            disabled={submitting}
            className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 rounded-lg hover:bg-slate-100 transition-colors disabled:opacity-50"
          >
            {t('catalog.cancel')}
          </button>

          {hasChildren ? (
            <>
              <button
                type="button"
                onClick={() => handle(false)}
                disabled={submitting}
                className="px-4 py-2 text-sm font-semibold text-amber-700 bg-amber-50 hover:bg-amber-100 rounded-lg ring-1 ring-inset ring-amber-600/20 transition-colors disabled:opacity-50"
              >
                {t('catalog.delete_service_orphan')}
              </button>
              <button
                type="button"
                onClick={() => handle(true)}
                disabled={submitting}
                className="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50"
              >
                {t('catalog.delete_service_cascade')}
              </button>
            </>
          ) : (
            <button
              type="button"
              onClick={() => handle(false)}
              disabled={submitting}
              className="px-4 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50"
            >
              {t('catalog.delete_service_simple')}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

DeleteServiceModal.propTypes = {
  service: PropTypes.shape({
    id: PropTypes.number.isRequired,
    name_es: PropTypes.string,
    name_en: PropTypes.string,
    children: PropTypes.array,
  }).isRequired,
  lang: PropTypes.string.isRequired,
  onCancel: PropTypes.func.isRequired,
  onConfirm: PropTypes.func.isRequired,
};

// ─── Main page ────────────────────────────────────────────────────────────────

export default function CatalogPage() {
  const { t, i18n } = useTranslation('common');
  const lang = i18n.language === 'es' ? 'es' : 'en';

  const [tree, setTree] = useState({ bundles: [], services: [], orphans: [], counts: { bundles: 0, services: 0, deliverables: 0 } });
  const [activeTab, setActiveTab] = useState(TAB_DELIVERABLES);
  const [isLoading, setIsLoading] = useState(true);
  const [fetchError, setFetchError] = useState('');

  // Modal state: { type: 'deliverable'|'service'|'package', item: object|null }
  const [modal, setModal] = useState(null);

  // Service-delete dialog state: holds the service being deleted, or null when closed.
  const [serviceToDelete, setServiceToDelete] = useState(null);

  // Active draggable id (for the DragOverlay ghost).
  const [activeDragId, setActiveDragId] = useState(null);

  // PointerSensor with a small activation distance so clicks on the handle
  // still feel responsive without accidentally starting a drag on regular clicks.
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } })
  );

  // ── Load tree ────────────────────────────────────────────────────────────
  // We load the tree and (in parallel) the flat list of all deliverables so
  // we can identify orphans (parent_id = null or parent missing from tree).
  const loadTree = useCallback(async () => {
    setIsLoading(true);
    setFetchError('');
    try {
      const [data, allDeliverables] = await Promise.all([
        catalogApi.getTree(),
        catalogApi.listByLevel('deliverable'),
      ]);

      const services = Array.isArray(data.services) ? data.services : [];

      // Collect ids of deliverables already nested under a service in the tree.
      const knownDeliverableIds = new Set();
      for (const svc of services) {
        for (const child of (svc.children ?? [])) {
          knownDeliverableIds.add(child.id);
        }
      }

      // Anything not nested under a service is treated as orphan.
      const orphans = (allDeliverables ?? []).filter(
        (d) => !knownDeliverableIds.has(d.id)
      );

      setTree({
        bundles: Array.isArray(data.bundles) ? data.bundles : [],
        services,
        orphans,
        counts: data.counts ?? { bundles: 0, services: 0, deliverables: 0 },
      });
    } catch (error) {
      setFetchError(
        error?.response?.data?.message ?? t('catalog.load_error')
      );
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadTree();
  }, [loadTree]);

  // ── Derived: orphan deliverables (no parent or unknown parent) ───────────
  // Orphans live on tree.orphans if backend ever exposes them, but we also
  // double-check by scanning known parent ids — defensive.
  const orphanDeliverables = useMemo(() => {
    const fromTree = Array.isArray(tree.orphans) ? tree.orphans : [];
    return fromTree;
  }, [tree]);

  // ── Derived: counts ──────────────────────────────────────────────────────
  const counts = useMemo(() => {
    const childDeliverables = tree.services.reduce(
      (acc, s) => acc + (Array.isArray(s.children) ? s.children.length : 0),
      0
    );
    const totalDeliverables = childDeliverables + orphanDeliverables.length;
    return {
      deliverables: tree.counts?.deliverables ?? totalDeliverables,
      services: tree.counts?.services ?? tree.services.length,
      bundles: tree.counts?.bundles ?? tree.bundles.length,
    };
  }, [tree, orphanDeliverables]);

  // ── Modal helpers ────────────────────────────────────────────────────────
  function openCreate(type) {
    setModal({ type, item: null });
  }

  function openEdit(type, item) {
    setModal({ type, item });
  }

  function closeModal() {
    setModal(null);
  }

  async function handleSave(payload, id) {
    if (id !== undefined) {
      await catalogApi.updateItem(id, payload);
    } else {
      await catalogApi.createItem(payload);
    }
    closeModal();
    await loadTree();
  }

  // Generic delete (used for deliverables and packages — services use modal).
  async function handleDelete(item, confirmKey) {
    const name = lang === 'es' ? item.name_es : item.name_en;
    const confirmed = window.confirm(t(confirmKey, { name }));
    if (!confirmed) return;

    try {
      await catalogApi.deleteItem(item.id);
      await loadTree();
    } catch (error) {
      const msgKey = error?.response?.data?.message;
      const message = msgKey
        ? t(msgKey, { defaultValue: msgKey })
        : t('catalog.delete_error');
      window.alert(message);
    }
  }

  // Delete a service after user chose cascade or orphan from the modal.
  async function confirmDeleteService(cascade) {
    if (!serviceToDelete) return;
    await catalogApi.deleteItem(serviceToDelete.id, cascade);
    setServiceToDelete(null);
    await loadTree();
  }

  // ── Drag & drop handlers ─────────────────────────────────────────────────

  function handleDragStart(event) {
    setActiveDragId(event.active?.id ?? null);
  }

  // Move a deliverable from source service → target service (or uncategorized).
  // Performs optimistic update then calls API; reverts on failure.
  async function handleDragEnd(event) {
    const { active, over } = event;
    setActiveDragId(null);
    if (!over) return;

    const deliverableId = active?.data?.current?.deliverableId;
    if (!deliverableId) return;

    // Determine the new parent_id (null when dropping in Uncategorized).
    let newParentId;
    const overId = String(over.id);
    if (overId === UNCATEGORIZED_ID) {
      newParentId = null;
    } else if (overId.startsWith('service-')) {
      newParentId = Number(overId.replace('service-', ''));
    } else {
      return;
    }

    // Find the deliverable and its current parent inside the tree.
    const snapshot = tree;
    let sourceServiceId = null;
    let deliverable = null;

    for (const svc of snapshot.services) {
      const found = (svc.children ?? []).find((d) => d.id === deliverableId);
      if (found) {
        sourceServiceId = svc.id;
        deliverable = found;
        break;
      }
    }
    if (!deliverable && Array.isArray(snapshot.orphans)) {
      const found = snapshot.orphans.find((d) => d.id === deliverableId);
      if (found) {
        sourceServiceId = null; // already uncategorized
        deliverable = found;
      }
    }
    if (!deliverable) return;

    // No-op if dropping in the same container.
    if (sourceServiceId === newParentId) return;

    // Build optimistic next-state.
    const nextServices = snapshot.services.map((svc) => {
      // Remove from source service.
      if (svc.id === sourceServiceId) {
        return {
          ...svc,
          children: (svc.children ?? []).filter((d) => d.id !== deliverableId),
        };
      }
      // Add to target service.
      if (svc.id === newParentId) {
        return {
          ...svc,
          children: [...(svc.children ?? []), { ...deliverable, parent_id: newParentId }],
        };
      }
      return svc;
    });

    let nextOrphans = Array.isArray(snapshot.orphans) ? [...snapshot.orphans] : [];
    if (sourceServiceId === null) {
      // Was orphan, remove it from orphans.
      nextOrphans = nextOrphans.filter((d) => d.id !== deliverableId);
    }
    if (newParentId === null) {
      // Moving to uncategorized.
      nextOrphans = [...nextOrphans, { ...deliverable, parent_id: null }];
    }

    setTree({ ...snapshot, services: nextServices, orphans: nextOrphans });

    // Persist to API.
    try {
      await catalogApi.updateItem(deliverableId, { parent_id: newParentId });
    } catch (error) {
      // Revert on failure.
      setTree(snapshot);
      const msgKey = error?.response?.data?.message;
      const message = msgKey
        ? t(msgKey, { defaultValue: msgKey })
        : t('catalog.move_error');
      window.alert(message);
    }
  }

  // Resolve the currently dragged deliverable (for the DragOverlay).
  const activeDeliverable = useMemo(() => {
    if (!activeDragId) return null;
    const id = Number(String(activeDragId).replace('deliverable-', ''));
    for (const svc of tree.services) {
      const found = (svc.children ?? []).find((d) => d.id === id);
      if (found) return found;
    }
    if (Array.isArray(tree.orphans)) {
      const found = tree.orphans.find((d) => d.id === id);
      if (found) return found;
    }
    return null;
  }, [activeDragId, tree]);

  // ── Action button (right side of header) ─────────────────────────────────
  function getCreateButton() {
    const cfg = {
      [TAB_DELIVERABLES]: {
        label: t('catalog.new_deliverable'),
        onClick: () => openCreate('deliverable'),
      },
      [TAB_SERVICES]: {
        label: t('catalog.new_service'),
        onClick: () => openCreate('service'),
      },
      [TAB_PACKAGES]: {
        label: t('catalog.new_package'),
        onClick: () => openCreate('package'),
      },
    }[activeTab];

    return (
      <button
        onClick={cfg.onClick}
        className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors"
      >
        <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        {cfg.label}
      </button>
    );
  }

  // Pseudo-service used for the Uncategorized accordion.
  const uncategorizedBucket = useMemo(() => ({
    id: UNCATEGORIZED_ID,
    label: t('catalog.uncategorized'),
    name_es: t('catalog.uncategorized'),
    name_en: t('catalog.uncategorized'),
    children: orphanDeliverables,
  }), [t, orphanDeliverables]);

  // ── Render ───────────────────────────────────────────────────────────────
  return (
    <>
      <div className="space-y-5">
        {/* Header */}
        <div className="flex items-center justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-slate-800">{t('catalog.title')}</h1>
            <p className="mt-0.5 text-sm text-slate-500">{t('catalog.subtitle')}</p>
          </div>
          {!isLoading && !fetchError && getCreateButton()}
        </div>

        {/* Tabs */}
        <div className="border-b border-slate-200">
          <nav className="flex gap-2 overflow-x-auto">
            <TabButton
              active={activeTab === TAB_DELIVERABLES}
              onClick={() => setActiveTab(TAB_DELIVERABLES)}
              icon={<IconDeliverable />}
              label={t('catalog.tabs.deliverables')}
              count={counts.deliverables}
            />
            <TabButton
              active={activeTab === TAB_SERVICES}
              onClick={() => setActiveTab(TAB_SERVICES)}
              icon={<IconService />}
              label={t('catalog.tabs.services')}
              count={counts.services}
            />
            <TabButton
              active={activeTab === TAB_PACKAGES}
              onClick={() => setActiveTab(TAB_PACKAGES)}
              icon={<IconPackage />}
              label={t('catalog.tabs.packages')}
              count={counts.bundles}
            />
          </nav>
        </div>

        {/* Loading */}
        {isLoading && (
          <div className="flex items-center justify-center py-20 gap-3">
            <svg className="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
              <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <p className="text-sm text-slate-500">{t('catalog.loading')}</p>
          </div>
        )}

        {/* Fetch error */}
        {!isLoading && fetchError && (
          <div className="rounded-xl bg-red-50 border border-red-200 px-5 py-4 flex items-start gap-3">
            <svg className="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" strokeWidth="1.75" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <div>
              <p className="text-sm font-medium text-red-700">{fetchError}</p>
              <button
                onClick={loadTree}
                className="mt-1 text-xs text-red-600 underline hover:text-red-800"
              >
                {t('common.try_again')}
              </button>
            </div>
          </div>
        )}

        {/* Content per tab */}
        {!isLoading && !fetchError && (
          <>
            {activeTab === TAB_DELIVERABLES && (
              <DndContext
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragStart={handleDragStart}
                onDragEnd={handleDragEnd}
                onDragCancel={() => setActiveDragId(null)}
              >
                <div className="space-y-3">
                  {tree.services.length === 0 && orphanDeliverables.length === 0 && (
                    <div className="text-center py-16 text-sm text-slate-400">
                      {t('catalog.empty_deliverables')}
                    </div>
                  )}
                  {tree.services.map((s) => (
                    <ServiceAccordion
                      key={s.id}
                      service={s}
                      lang={lang}
                      droppableId={`service-${s.id}`}
                      onEditDeliverable={(d) => openEdit('deliverable', d)}
                      onDeleteDeliverable={(d) =>
                        handleDelete(d, 'catalog.deliverable.delete_confirm')
                      }
                    />
                  ))}
                  {/* Uncategorized accordion — only when there are orphans. */}
                  {orphanDeliverables.length > 0 && (
                    <ServiceAccordion
                      key={UNCATEGORIZED_ID}
                      service={uncategorizedBucket}
                      lang={lang}
                      droppableId={UNCATEGORIZED_ID}
                      variant="uncategorized"
                      onEditDeliverable={(d) => openEdit('deliverable', d)}
                      onDeleteDeliverable={(d) =>
                        handleDelete(d, 'catalog.deliverable.delete_confirm')
                      }
                    />
                  )}
                </div>

                <DragOverlay dropAnimation={null}>
                  {activeDeliverable ? (
                    <DragOverlayRow deliverable={activeDeliverable} lang={lang} />
                  ) : null}
                </DragOverlay>
              </DndContext>
            )}

            {activeTab === TAB_SERVICES && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {tree.services.length === 0 && (
                  <div className="md:col-span-2 text-center py-16 text-sm text-slate-400">
                    {t('catalog.empty_services')}
                  </div>
                )}
                {tree.services.map((s) => (
                  <ServiceCard
                    key={s.id}
                    service={s}
                    lang={lang}
                    onEdit={(item) => openEdit('service', item)}
                    onDelete={(item) => setServiceToDelete(item)}
                  />
                ))}
              </div>
            )}

            {activeTab === TAB_PACKAGES && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {tree.bundles.length === 0 && (
                  <div className="md:col-span-2 text-center py-16 text-sm text-slate-400">
                    {t('catalog.empty_packages')}
                  </div>
                )}
                {tree.bundles.map((b) => (
                  <PackageCard
                    key={b.id}
                    bundle={b}
                    lang={lang}
                    onEdit={(item) => openEdit('package', item)}
                    onDelete={(item) =>
                      handleDelete(item, 'catalog.package.delete_confirm')
                    }
                  />
                ))}
              </div>
            )}
          </>
        )}
      </div>

      {/* Modals */}
      {modal?.type === 'deliverable' && (
        <DeliverableFormModal
          deliverable={modal.item}
          services={tree.services}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}
      {modal?.type === 'service' && (
        <ServiceFormModal
          service={modal.item}
          services={tree.services}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}
      {modal?.type === 'package' && (
        <PackageFormModal
          bundle={modal.item}
          services={tree.services}
          onClose={closeModal}
          onSave={handleSave}
        />
      )}

      {/* Service delete confirmation modal */}
      {serviceToDelete && (
        <DeleteServiceModal
          service={serviceToDelete}
          lang={lang}
          onCancel={() => setServiceToDelete(null)}
          onConfirm={confirmDeleteService}
        />
      )}
    </>
  );
}
