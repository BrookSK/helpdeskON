-- Adiciona o status 'convertida' ao ENUM da coluna status em agenda_meetings
ALTER TABLE agenda_meetings
    MODIFY COLUMN status ENUM('a_agendar','agendada','confirmada','realizada','remarcada','cancelada','convertida')
    NOT NULL DEFAULT 'a_agendar';
