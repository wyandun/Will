/**
 * @vitest-environment jsdom
 *
 * Unit tests for ProcessMapDetailPage (the map editor / hierarchy view).
 *
 * Covers: loading the tree, the 3 fixed divisions (STRATEGIC / VALUE CHAIN /
 * SUPPORT) with macro counters, the CLIENT NEED / CLIENT SATISFACTION labels,
 * macroprocess badge + process/sub-process codes, the sub-sub-process counter,
 * and write-gating of the Edit Division / Add Macroprocess controls.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

// react-router-dom — id param + Link/navigate stubs
vi.mock('react-router-dom', () => ({
  useParams: () => ({ id: '1' }),
  useNavigate: () => vi.fn(),
  // eslint-disable-next-line react/prop-types
  Link: ({ to, children }) => <a href={to}>{children}</a>,
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({ t: (key) => key, i18n: { language: 'en' } }),
}));

let mockCanWrite = true;
vi.mock('../../hooks/usePermissions', () => ({
  usePermissions: () => ({ canWrite: () => mockCanWrite }),
}));

const mockGet = vi.fn();
const mockCreateSubProcess = vi.fn();
const mockCreateProcess = vi.fn();
vi.mock('../../api/processMaps', () => ({
  processMapsApi: {
    get: (...a) => mockGet(...a),
    createSubProcess: (...a) => mockCreateSubProcess(...a),
    createProcess: (...a) => mockCreateProcess(...a),
  },
}));

import ProcessMapDetailPage from './ProcessMapDetailPage';

const tree = {
  id: 1,
  name_en: 'Operations Map',
  name_es: 'Mapa Operativo',
  categories: [
    {
      id: 1,
      type: 'strategic',
      name_en: 'Strategic',
      name_es: 'Estratégicos',
      processes: [
        {
          id: 10,
          code: 'DEV',
          name_en: 'Development',
          sub_processes: [
            {
              id: 100,
              code: 'DE-P01',
              name_en: 'Design',
              has_bpmn: true,
              sub_sub_processes_count: 2,
              sub_sub_processes: [
                { id: 1000, code: 'DE-P01-S01', name_en: 'Wireframe', has_bpmn: false },
              ],
            },
          ],
        },
      ],
    },
    { id: 2, type: 'value_chain', name_en: 'Value Chain', name_es: 'Cadena', processes: [] },
    { id: 3, type: 'support', name_en: 'Support', name_es: 'Apoyo', processes: [] },
  ],
};

beforeEach(() => {
  vi.clearAllMocks();
  mockCanWrite = true;
  mockGet.mockResolvedValue({ data: tree });
  mockCreateSubProcess.mockResolvedValue({});
  mockCreateProcess.mockResolvedValue({});
});

describe('ProcessMapDetailPage', () => {
  it('renders the map title and back link after loading', async () => {
    render(<ProcessMapDetailPage />);
    expect(await screen.findByText('Operations Map')).toBeInTheDocument();
    expect(screen.getByText(/processMaps\.detail\.back/)).toBeInTheDocument();
  });

  it('renders the CLIENT NEED and CLIENT SATISFACTION side labels', async () => {
    render(<ProcessMapDetailPage />);
    expect(await screen.findByText('processMaps.detail.client_need')).toBeInTheDocument();
    expect(screen.getByText('processMaps.detail.client_satisfaction')).toBeInTheDocument();
  });

  it('renders the 3 fixed divisions', async () => {
    render(<ProcessMapDetailPage />);
    expect(await screen.findByText('Strategic')).toBeInTheDocument();
    expect(screen.getByText('Value Chain')).toBeInTheDocument();
    expect(screen.getByText('Support')).toBeInTheDocument();
  });

  it('shows the macro counter per division', async () => {
    render(<ProcessMapDetailPage />);
    // Strategic has 1 macro → singular key; empty divisions → plural key.
    expect(await screen.findByText(/1\s+processMaps\.detail\.macro_singular/)).toBeInTheDocument();
    expect(screen.getAllByText(/0\s+processMaps\.detail\.macro_plural/).length).toBe(2);
  });

  it('renders the macro badge and the process / sub-process codes', async () => {
    render(<ProcessMapDetailPage />);
    expect(await screen.findByText('DEV')).toBeInTheDocument(); // badge (first 3 chars)
    expect(screen.getByText('DE-P01')).toBeInTheDocument();
    expect(screen.getByText('DE-P01-S01')).toBeInTheDocument();
  });

  it('shows the sub-sub-process counter next to a process', async () => {
    render(<ProcessMapDetailPage />);
    await screen.findByText('DE-P01');
    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('shows Edit Division and Add Macroprocess controls when the user can write', async () => {
    render(<ProcessMapDetailPage />);
    expect(await screen.findByTitle('processMaps.detail.edit_division')).toBeInTheDocument();
    expect(screen.getAllByText('processMaps.detail.add_macro').length).toBeGreaterThan(0);
  });

  it('hides write controls for a read-only user', async () => {
    mockCanWrite = false;
    render(<ProcessMapDetailPage />);
    await screen.findByText('Strategic');
    expect(screen.queryByTitle('processMaps.detail.edit_division')).not.toBeInTheDocument();
    expect(screen.queryByText('processMaps.detail.add_macro')).not.toBeInTheDocument();
  });

  it('shows a single name field plus an optional description in the create/edit modal (no separate ES/EN inputs)', async () => {
    const user = userEvent.setup();
    render(<ProcessMapDetailPage />);
    await screen.findByText('DE-P01');

    // Open the "add sub-process" modal (a name field + optional description, no code).
    await user.click(screen.getByTitle('processMaps.detail.add_process'));

    // Two text inputs: the collapsed single name field + the optional description textarea.
    expect(screen.getAllByRole('textbox')).toHaveLength(2);
    expect(screen.getByText('processMaps.detail.field_name')).toBeInTheDocument();
    expect(screen.getByText('processMaps.detail.field_description')).toBeInTheDocument();
  });

  it('saves the single name into both name_es and name_en (null description when blank)', async () => {
    const user = userEvent.setup();
    render(<ProcessMapDetailPage />);
    await screen.findByText('DE-P01');

    await user.click(screen.getByTitle('processMaps.detail.add_process'));
    // First textbox is the name field; description is left empty.
    await user.type(screen.getAllByRole('textbox')[0], 'Recruiting');
    await user.click(screen.getByText('processMaps.detail.create'));

    expect(mockCreateSubProcess).toHaveBeenCalledWith(10, {
      name_es: 'Recruiting',
      name_en: 'Recruiting',
      description: null,
    });
  });

  it('sends the optional description through when filled', async () => {
    const user = userEvent.setup();
    render(<ProcessMapDetailPage />);
    await screen.findByText('DE-P01');

    await user.click(screen.getByTitle('processMaps.detail.add_process'));
    const [nameInput, descInput] = screen.getAllByRole('textbox');
    await user.type(nameInput, 'Recruiting');
    await user.type(descInput, 'Hire the best');
    await user.click(screen.getByText('processMaps.detail.create'));

    expect(mockCreateSubProcess).toHaveBeenCalledWith(10, {
      name_es: 'Recruiting',
      name_en: 'Recruiting',
      description: 'Hire the best',
    });
  });
});
