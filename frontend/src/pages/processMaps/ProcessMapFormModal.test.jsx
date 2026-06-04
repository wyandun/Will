/**
 * @vitest-environment jsdom
 *
 * Unit tests for ProcessMapFormModal (the "New map" modal).
 *
 * Covers: the FRANCHISE / CLIENT (with email) / MAP NAME / DESCRIPTION fields,
 * the client select being disabled until a franchise is chosen, required-field
 * validation, and the create payload sent to onSave.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key, i18n: { language: 'en' } }),
}));

import ProcessMapFormModal from './ProcessMapFormModal';

const franchises = [
  { id: 1, name: 'SM Florida' },
  { id: 2, name: 'SM Texas' },
];
const companies = [
  { id: 10, name: 'Acme', sm_franchise_id: 1, email: 'ops@acme.com' },
  { id: 11, name: 'Globex', sm_franchise_id: 2 },
];

function setup(overrides = {}) {
  const onSave = overrides.onSave ?? vi.fn().mockResolvedValue();
  const onClose = overrides.onClose ?? vi.fn();
  render(
    <ProcessMapFormModal
      franchises={franchises}
      companies={companies}
      onSave={onSave}
      onClose={onClose}
    />
  );
  return { onSave, onClose };
}

beforeEach(() => vi.clearAllMocks());

describe('ProcessMapFormModal', () => {
  it('renders all four fields', () => {
    setup();
    expect(screen.getByText('processMaps.modal_franchise_label')).toBeInTheDocument();
    expect(screen.getByText('processMaps.modal_client_label')).toBeInTheDocument();
    expect(screen.getByText('processMaps.modal_name_label')).toBeInTheDocument();
    expect(screen.getByText('processMaps.modal_description_label')).toBeInTheDocument();
  });

  it('disables the client select until a franchise is chosen', async () => {
    const user = userEvent.setup();
    setup();

    const [franchiseSelect, clientSelect] = screen.getAllByRole('combobox');
    expect(clientSelect).toBeDisabled();

    await user.selectOptions(franchiseSelect, '1');
    expect(clientSelect).toBeEnabled();
  });

  it('lists clients with their email, scoped to the franchise', async () => {
    const user = userEvent.setup();
    setup();

    const [franchiseSelect] = screen.getAllByRole('combobox');
    await user.selectOptions(franchiseSelect, '1');

    expect(screen.getByText('Acme — ops@acme.com')).toBeInTheDocument();
    expect(screen.queryByText('Globex')).not.toBeInTheDocument();
  });

  it('shows validation errors when submitting empty', async () => {
    const user = userEvent.setup();
    const { onSave } = setup();

    await user.click(screen.getByText('processMaps.modal_submit'));

    expect(screen.getByText('processMaps.modal_franchise_required')).toBeInTheDocument();
    expect(screen.getByText('processMaps.modal_client_required')).toBeInTheDocument();
    expect(screen.getByText('processMaps.modal_name_required')).toBeInTheDocument();
    expect(onSave).not.toHaveBeenCalled();
  });

  it('submits the expected payload (type=custom, name mirrored es/en)', async () => {
    const user = userEvent.setup();
    const { onSave } = setup();

    const [franchiseSelect, clientSelect] = screen.getAllByRole('combobox');
    await user.selectOptions(franchiseSelect, '1');
    await user.selectOptions(clientSelect, '10');
    await user.type(screen.getByPlaceholderText('processMaps.modal_name_placeholder'), 'Onboarding');
    await user.type(
      screen.getByPlaceholderText('processMaps.modal_description_placeholder'),
      'Notes'
    );

    await user.click(screen.getByText('processMaps.modal_submit'));

    expect(onSave).toHaveBeenCalledWith({
      company_id: 10,
      type: 'custom',
      name_es: 'Onboarding',
      name_en: 'Onboarding',
      description: 'Notes',
    });
  });
});
