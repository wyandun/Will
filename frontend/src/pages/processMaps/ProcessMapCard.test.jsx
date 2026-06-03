/**
 * @vitest-environment jsdom
 *
 * Unit tests for ProcessMapCard.
 *
 * Covers: icon + name (locale-aware) + description, franchise & client rows,
 * the "Open →" link target, and the hover/write-gated trash delete button.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// react-router-dom — Link rendered as a plain anchor
vi.mock('react-router-dom', () => ({
  // eslint-disable-next-line react/prop-types
  Link: ({ to, children }) => <a href={to}>{children}</a>,
}));

// i18n — transparent (returns the key), English language
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key) => key,
    i18n: { language: 'en' },
  }),
}));

// Permissions — toggled per test
let mockCanWrite = true;
vi.mock('../../hooks/usePermissions', () => ({
  usePermissions: () => ({ canWrite: () => mockCanWrite }),
}));

import ProcessMapCard from './ProcessMapCard';

const map = {
  id: 7,
  name_es: 'Mapa Operativo',
  name_en: 'Operations Map',
  description: 'Core operating model',
  company: { id: 3, name: 'Acme Co' },
  franchise: { id: 1, name: 'SM Florida' },
};

beforeEach(() => {
  vi.clearAllMocks();
  mockCanWrite = true;
});

describe('ProcessMapCard', () => {
  it('renders the English name, description, franchise and client', () => {
    render(<ProcessMapCard map={map} onDelete={vi.fn()} />);

    expect(screen.getByText('Operations Map')).toBeInTheDocument();
    expect(screen.getByText('Core operating model')).toBeInTheDocument();
    expect(screen.getByText('SM Florida')).toBeInTheDocument();
    expect(screen.getByText('Acme Co')).toBeInTheDocument();
  });

  it('links "Open →" to the map detail route', () => {
    render(<ProcessMapCard map={map} onDelete={vi.fn()} />);

    const link = screen.getByText(/processMaps\.open/).closest('a');
    expect(link).toHaveAttribute('href', '/processes/7');
  });

  it('shows the delete button and fires onDelete when user can write', async () => {
    const onDelete = vi.fn();
    const user = userEvent.setup();
    render(<ProcessMapCard map={map} onDelete={onDelete} />);

    const deleteBtn = screen.getByLabelText('processMaps.delete_btn');
    await user.click(deleteBtn);

    expect(onDelete).toHaveBeenCalledWith(map);
  });

  it('hides the delete button when the user cannot write', () => {
    mockCanWrite = false;
    render(<ProcessMapCard map={map} onDelete={vi.fn()} />);

    expect(screen.queryByLabelText('processMaps.delete_btn')).not.toBeInTheDocument();
  });

  it('falls back to a dash for a missing franchise/client', () => {
    render(<ProcessMapCard map={{ id: 1, name_en: 'X' }} onDelete={vi.fn()} />);

    // Both franchise and company rows render the em dash fallback.
    expect(screen.getAllByText('—').length).toBeGreaterThanOrEqual(2);
  });
});
