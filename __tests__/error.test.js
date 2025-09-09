import { jest } from '@jest/globals';

describe('handleError', () => {
  it('logs and alerts the user message', async () => {
    const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {});
    const alertMock = jest.fn();
    global.window = { alert: alertMock };
    global.alert = alertMock;
    const { handleError } = await import('../PetIA/js/error.js');
    handleError(new Error('fail'), 'Oops');
    expect(consoleSpy).toHaveBeenCalled();
    expect(alertMock).toHaveBeenCalledWith('Oops');
    consoleSpy.mockRestore();
    delete global.window;
    delete global.alert;
  });
});
